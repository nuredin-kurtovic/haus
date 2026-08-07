<?php

namespace Tests\Feature\Services;

use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\VisitSource;
use App\Models\HomeRecord;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use App\Models\Technician;
use App\Models\User;
use App\Services\JobCompletionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pravila naplate pri zatvaranju naloga. Redoslijed je kredit, pa izlazak,
 * pa naplata. Garancija i pregled se ne naplacuju.
 */
class JobCompletionServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $klijent;

    private User $majstor;

    private Subscription $pretplata;

    private SubscriptionProperty $stan;

    private JobCompletionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();
        Storage::fake('public');

        Carbon::setTestNow(Carbon::create(2026, 8, 10, 11, 0, 0));

        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $this->majstor = User::where('email', 'damir@haus.ba')->firstOrFail();
        $this->pretplata = $this->klijent->activeSubscription;
        $this->stan = $this->pretplata->properties->first();
        $this->service = app(JobCompletionService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_kredit_se_trosi_prije_izlaska(): void
    {
        $this->pretplata->update(['free_interventions' => 1]);

        $job = $this->zatvori($this->nalog());

        $this->assertSame(VisitSource::Kredit, $job->visit_source);
        $this->assertSame(0, (int) $this->pretplata->refresh()->free_interventions);
        $this->assertSame(3, (int) $this->stan->refresh()->remaining_visits, 'Kredit ne dira izlaske.');

        $invoice = Invoice::where('job_id', $job->id)->firstOrFail();
        $this->assertSame(0.0, (float) $invoice->labor_total);
        $this->assertSame(0.0, (float) $invoice->total);
        $this->assertSame(InvoiceStatus::BezNaplate, $invoice->status);
    }

    public function test_bez_kredita_se_trosi_izlazak(): void
    {
        $job = $this->zatvori($this->nalog());

        $this->assertSame(VisitSource::Izlazak, $job->visit_source);
        $this->assertSame(2, (int) $this->stan->refresh()->remaining_visits);
        $this->assertSame(0.0, (float) Invoice::where('job_id', $job->id)->value('labor_total'));
    }

    public function test_bez_kredita_i_izlazaka_rad_se_naplacuje(): void
    {
        $this->stan->update(['remaining_visits' => 0]);

        $job = $this->zatvori($this->nalog());

        $this->assertSame(VisitSource::Naplata, $job->visit_source);
        $this->assertSame(0, (int) $this->stan->refresh()->remaining_visits);

        $invoice = Invoice::where('job_id', $job->id)->firstOrFail();
        // HAUS Plus, popust na rad 25 posto: 55 KM postaje 41 KM.
        $this->assertSame(41.0, (float) $invoice->labor_total);
        $this->assertSame(41.0, (float) $invoice->total);
        $this->assertSame(InvoiceStatus::Nenaplaceno, $invoice->status);
    }

    public function test_materijal_se_naplacuje_i_kad_je_izlazak_pokriven(): void
    {
        $job = $this->zatvori($this->nalog(), materijali: [
            ['name' => 'Sifon', 'purchase_price' => 10, 'qty' => 1],
        ]);

        $invoice = Invoice::where('job_id', $job->id)->firstOrFail();

        $this->assertSame(0.0, (float) $invoice->labor_total);
        // Nabavna 10, marza 20 posto, popust na materijal 5 posto.
        $this->assertSame(11.40, (float) $invoice->material_total);
        $this->assertSame(11.40, (float) $invoice->total);
        $this->assertSame(InvoiceStatus::Nenaplaceno, $invoice->status);
    }

    public function test_garancijski_nalog_ne_naplacuje_i_ne_trosi_nista(): void
    {
        $original = $this->nalog();
        $original->update([
            'status' => JobStatus::Zavrseno,
            'warranty_until' => Carbon::create(2027, 3, 1),
            'completed_at' => Carbon::now()->subMonth(),
        ]);

        $garancija = $this->nalog([
            'type' => JobType::Garancija,
            'parent_job_id' => $original->id,
        ]);

        $job = $this->zatvori($garancija, materijali: [
            ['name' => 'Dihtung', 'purchase_price' => 8, 'qty' => 1],
        ]);

        $this->assertNull($job->visit_source);
        $this->assertSame(3, (int) $this->stan->refresh()->remaining_visits);
        $this->assertSame(0, (int) $this->pretplata->refresh()->free_interventions);
        // Garancija nastavlja garanciju originala, ne pravi novu.
        $this->assertSame('2027-03-01', $job->warranty_until->toDateString());

        $invoice = Invoice::where('job_id', $job->id)->firstOrFail();
        $this->assertSame(0.0, (float) $invoice->material_total);
        $this->assertSame(0.0, (float) $invoice->total);
        $this->assertSame(InvoiceStatus::BezNaplate, $invoice->status);
    }

    public function test_pregled_trosi_pravo_na_pregled(): void
    {
        $job = $this->zatvori($this->nalog(['type' => JobType::Pregled]));

        $this->assertNull($job->visit_source);
        $this->assertSame(3, (int) $this->stan->refresh()->remaining_visits);
        $this->assertSame(0, (int) $this->stan->refresh()->remaining_inspections);

        $this->assertSame('pregled', HomeRecord::where('job_id', $job->id)->value('type')->value);
        $this->assertSame(0.0, (float) Invoice::where('job_id', $job->id)->value('total'));
    }

    public function test_garancija_se_racuna_iz_paketa_kad_nema_originala(): void
    {
        $job = $this->zatvori($this->nalog());

        // HAUS Plus daje 12 mjeseci garancije na rad.
        $this->assertSame('2027-08-10', $job->warranty_until->toDateString());
    }

    public function test_nalog_van_stanja_u_toku_se_ne_zatvara(): void
    {
        $job = $this->nalog(['status' => JobStatus::Zakazano]);

        $this->expectException(ValidationException::class);

        $this->zatvori($job);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function nalog(array $overrides = []): Job
    {
        return Job::create(array_merge([
            'number' => Job::nextNumber(),
            'user_id' => $this->klijent->id,
            'subscription_id' => $this->pretplata->id,
            'subscription_property_id' => $this->stan->id,
            'price_category_id' => PriceCategory::where('slug', 'vodoinstalacije')->value('id'),
            'technician_id' => Technician::query()->value('id'),
            'type' => JobType::Redovno,
            'status' => JobStatus::UToku,
            'description' => 'Slavina u kupatilu curi.',
            'deadline_at' => Carbon::now()->addHours(48),
        ], $overrides));
    }

    /**
     * @param  array<int, array{name: string, purchase_price: float|int, qty: float|int}>  $materijali
     */
    private function zatvori(Job $job, array $materijali = []): Job
    {
        $stavka = PriceItem::where('name', 'Zamjena baterije (lavabo ili sudopera)')->firstOrFail();

        return $this->service->complete(
            job: $job,
            findings: 'Zamijenjena baterija na lavabou.',
            items: [['price_item_id' => $stavka->id, 'qty' => 1]],
            materials: $materijali,
            photosBefore: [UploadedFile::fake()->image('prije.jpg')],
            photosAfter: [UploadedFile::fake()->image('poslije.jpg')],
            actor: $this->majstor,
        );
    }
}
