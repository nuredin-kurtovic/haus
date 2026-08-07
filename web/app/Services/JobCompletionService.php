<?php

namespace App\Services;

use App\Enums\HomeRecordType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\JobPhotoType;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\VisitSource;
use App\Mail\IzvjestajMail;
use App\Models\HomeRecord;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\JobMaterial;
use App\Models\JobPhoto;
use App\Models\Package;
use App\Models\PriceItem;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Zavrsetak naloga. Jedno mjesto za majstora i za dispecera.
 *
 * Pravila naplate koja ovdje vaze (ista su i u docs/API.md):
 * - kredit (besplatna intervencija) se trosi prije izlaska iz paketa,
 *   izlazak prije naplate. Odabrani izvor se upisuje u jobs.visit_source.
 * - kredit i izlazak pokrivaju rad, pa je labor_total na fakturi 0. Stavke se
 *   ipak upisuju sa svojim iznosom, da klijent vidi vrijednost koju je dobio.
 * - materijal se naplacuje uvijek kad ga ima, osim na garancijskom nalogu.
 * - kad kredita i izlazaka nema, visit_source je naplata i rad se naplacuje.
 * - garancija: nista se ne naplacuje i nista ne trosi.
 * - pregled: rad je pokriven pravom na pregled, trosi remaining_inspections.
 */
class JobCompletionService
{
    public function __construct(
        private readonly PriceCalculator $prices,
        private readonly SettingsService $settings,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array<int, array{price_item_id: int|string, qty: int|string}>  $items
     * @param  array<int, array{name: string, purchase_price: int|float|string, qty: int|float|string}>  $materials
     * @param  array<int, UploadedFile>  $photosBefore
     * @param  array<int, UploadedFile>  $photosAfter
     */
    public function complete(
        Job $job,
        string $findings,
        array $items,
        array $materials,
        array $photosBefore,
        array $photosAfter,
        User $actor,
    ): Job {
        if ($job->status !== JobStatus::UToku) {
            throw ValidationException::withMessages([
                'status' => 'Nalog se zatvara samo iz stanja u toku. Prvo pokrenite izlazak.',
            ]);
        }

        $job->loadMissing(['subscription.package', 'property', 'parent', 'category', 'user']);

        $zavrsen = DB::transaction(fn (): Job => $this->knjizi($job, $findings, $items, $materials, $actor));

        // Fajlovi, obavjestenje i izvjestaj idu tek kad transakcija prodje.
        $this->spremiFotografije($zavrsen, $photosBefore, JobPhotoType::Prije);
        $this->spremiFotografije($zavrsen, $photosAfter, JobPhotoType::Poslije);

        $this->javiKlijentu($zavrsen);

        return $zavrsen->refresh();
    }

    /**
     * Sve upisano u bazu ide u jednoj transakciji.
     *
     * @param  array<int, array{price_item_id: int|string, qty: int|string}>  $items
     * @param  array<int, array{name: string, purchase_price: int|float|string, qty: int|float|string}>  $materials
     */
    private function knjizi(Job $job, string $findings, array $items, array $materials, User $actor): Job
    {
        $subscription = $job->subscription;
        $package = $subscription?->package;

        $laborDiscount = (int) ($package->labor_discount_pct ?? 0);
        $materialDiscount = (int) ($package->material_discount_pct ?? 0);
        $markup = (int) $this->settings->get('materijal_marza_pct', 20);

        $laborTotal = $this->upisiStavke($job, $items, $laborDiscount);
        $materialTotal = $this->upisiMaterijal($job, $materials, $markup, $materialDiscount);

        $visitSource = $this->potrosiPravo($job, $subscription);

        $naplacujeRad = $job->type === JobType::Redovno && $visitSource === VisitSource::Naplata;
        $naplacujeMaterijal = $job->type !== JobType::Garancija;

        $naplativRad = $naplacujeRad ? $laborTotal : 0.0;
        $naplativMaterijal = $naplacujeMaterijal ? $materialTotal : 0.0;
        $ukupno = round($naplativRad + $naplativMaterijal, 2);

        $completedAt = Carbon::now();

        $job->forceFill([
            'status' => JobStatus::Zavrseno,
            'findings' => $findings,
            'completed_at' => $completedAt,
            'visit_source' => $visitSource,
            'warranty_until' => $this->garancijaDo($job, $package, $completedAt),
            // Nalog koji majstor zatvori, a nije mu bio dodijeljen, ostaje na njemu.
            'technician_id' => $job->technician_id ?? $actor->technician?->id,
        ])->save();

        Invoice::create([
            'number' => Invoice::nextNumber(),
            'user_id' => $job->user_id,
            'subscription_id' => $job->subscription_id,
            'job_id' => $job->id,
            'type' => InvoiceType::Rad,
            'labor_total' => $naplativRad,
            'material_total' => $naplativMaterijal,
            'total' => $ukupno,
            'status' => $ukupno > 0 ? InvoiceStatus::Nenaplaceno : InvoiceStatus::BezNaplate,
        ]);

        $this->upisiUKarton($job, $findings, $completedAt);

        return $job;
    }

    /**
     * Snapshot cijene i popusta. Kasnija objava cjenovnika ne mijenja zatvoren nalog.
     *
     * @param  array<int, array{price_item_id: int|string, qty: int|string}>  $items
     */
    private function upisiStavke(Job $job, array $items, int $discountPct): float
    {
        $ukupno = 0.0;

        foreach ($items as $red) {
            $priceItem = PriceItem::query()->find((int) $red['price_item_id']);

            if (! $priceItem) {
                continue;
            }

            $qty = max(1, (int) ($red['qty'] ?? 1));
            $base = (float) $priceItem->base_price;
            $lineTotal = $this->prices->laborLineTotal($base, $qty, $discountPct);

            JobItem::create([
                'job_id' => $job->id,
                'price_item_id' => $priceItem->id,
                'name' => $priceItem->name,
                'qty' => $qty,
                'base_price' => $base,
                'discount_pct' => $discountPct,
                'line_total' => $lineTotal,
            ]);

            $ukupno += $lineTotal;
        }

        return round($ukupno, 2);
    }

    /**
     * Materijal: nabavna cijena, marza iz postavki, pa popust paketa.
     *
     * @param  array<int, array{name: string, purchase_price: int|float|string, qty: int|float|string}>  $materials
     */
    private function upisiMaterijal(Job $job, array $materials, int $markupPct, int $discountPct): float
    {
        $ukupno = 0.0;

        foreach ($materials as $red) {
            $purchase = (float) $red['purchase_price'];
            $qty = (float) ($red['qty'] ?? 1);

            if ($qty <= 0) {
                continue;
            }

            $lineTotal = $this->prices->materialLineTotal($purchase, $qty, $markupPct, $discountPct);

            JobMaterial::create([
                'job_id' => $job->id,
                'name' => (string) $red['name'],
                'purchase_price' => $purchase,
                'qty' => $qty,
                'markup_pct' => $markupPct,
                'discount_pct' => $discountPct,
                'line_total' => $lineTotal,
            ]);

            $ukupno += $lineTotal;
        }

        return round($ukupno, 2);
    }

    /**
     * Redoslijed trosenja: kredit, pa izlazak, pa naplata.
     * Garancija ne trosi nista, pregled trosi pravo na pregled.
     */
    private function potrosiPravo(Job $job, ?Subscription $subscription): ?VisitSource
    {
        if ($job->type === JobType::Garancija) {
            return null;
        }

        $property = $job->subscription_property_id
            ? SubscriptionProperty::query()->whereKey($job->subscription_property_id)->lockForUpdate()->first()
            : null;

        if ($job->type === JobType::Pregled) {
            if ($property && $property->remaining_inspections > 0) {
                $property->decrement('remaining_inspections');
            }

            return null;
        }

        if ($subscription) {
            $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->first();

            if ($locked && $locked->free_interventions > 0) {
                $locked->decrement('free_interventions');

                return VisitSource::Kredit;
            }
        }

        if ($property && $property->remaining_visits > 0) {
            $property->decrement('remaining_visits');

            return VisitSource::Izlazak;
        }

        return VisitSource::Naplata;
    }

    /**
     * Garancija na rad. Garancijski nalog nastavlja garanciju originala.
     */
    private function garancijaDo(Job $job, ?Package $package, Carbon $completedAt): ?Carbon
    {
        if ($job->type === JobType::Garancija && $job->parent?->warranty_until) {
            return Carbon::instance($job->parent->warranty_until);
        }

        $mjeseci = (int) ($package->warranty_months ?? 0);

        if ($mjeseci <= 0) {
            return null;
        }

        return $completedAt->copy()->addMonths($mjeseci);
    }

    /**
     * HAUS Karton: svaki zatvoren nalog ostavlja red na adresi.
     */
    private function upisiUKarton(Job $job, string $findings, Carbon $completedAt): void
    {
        if (! $job->subscription_property_id) {
            return;
        }

        HomeRecord::create([
            'subscription_property_id' => $job->subscription_property_id,
            'job_id' => $job->id,
            'type' => $job->type === JobType::Pregled ? HomeRecordType::Pregled : HomeRecordType::Intervencija,
            'title' => $this->naslovKartona($job),
            'body' => $findings,
            'recorded_at' => $completedAt,
        ]);
    }

    private function naslovKartona(Job $job): string
    {
        $kategorija = $job->category?->name ?? 'Intervencija';

        return match ($job->type) {
            JobType::Pregled => 'Pregled: '.$kategorija,
            JobType::Garancija => 'Garancijski izlazak: '.$kategorija,
            default => $kategorija,
        };
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    private function spremiFotografije(Job $job, array $photos, JobPhotoType $type): void
    {
        foreach ($photos as $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }

            JobPhoto::create([
                'job_id' => $job->id,
                'type' => $type,
                'path' => $photo->store('jobs/'.$job->id, JobPhoto::DISK),
            ]);
        }
    }

    /**
     * Obavjestenje o zavrsetku i izvjestaj na mejl, oba poslije commita.
     */
    private function javiKlijentu(Job $job): void
    {
        $job->loadMissing(['user', 'items', 'materials', 'photos', 'invoice', 'category']);

        $user = $job->user;

        if (! $user) {
            return;
        }

        $this->notifications->send($user, 'zavrseno', [
            'broj' => $job->number,
            'garancija_datum' => $job->warranty_until?->format('d.m.Y') ?? '',
        ], $job);

        Mail::to($user->email)->queue(new IzvjestajMail($job));
    }
}
