<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\JobPhotoType;
use App\Enums\JobStatus;
use App\Enums\VisitSource;
use App\Mail\IzvjestajMail;
use App\Models\HomeRecord;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\NotificationLog;
use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Models\SubscriptionProperty;
use App\Models\Technician;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pun zivotni ciklus naloga kroz API: klijent prijavi, dispecer dodijeli i
 * zakaze, majstor krene i zatvori nalog.
 */
class JobLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $klijent;

    private User $dispecer;

    private User $majstor;

    private Technician $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();
        Storage::fake('public');

        // Petak, 10:00. Termin ide u ponedjeljak da radno vrijeme bude jasno.
        Carbon::setTestNow(Carbon::create(2026, 8, 7, 10, 0, 0));

        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $this->dispecer = User::where('email', 'dispecer@haus.ba')->firstOrFail();
        $this->majstor = User::where('email', 'damir@haus.ba')->firstOrFail();
        $this->technician = $this->majstor->technician;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_pun_ciklus_od_prijave_do_zatvaranja(): void
    {
        // 1. Klijent prijavljuje kvar.
        $prijava = $this->actingAs($this->klijent, 'sanctum')
            ->postJson('/api/v1/client/jobs', [
                'price_category_id' => PriceCategory::where('slug', 'vodoinstalacije')->value('id'),
                'description' => 'Slavina u kupatilu curi cijeli dan.',
                'is_emergency' => false,
            ])
            ->assertCreated();

        $jobId = (int) $prijava->json('job.id');

        // 2. Dispecer dodjeljuje majstora i zakazuje prozor od 2 sata.
        $this->actingAs($this->dispecer, 'sanctum')
            ->patchJson('/api/v1/admin/jobs/'.$jobId, [
                'technician_id' => $this->technician->id,
                'scheduled_window_start' => '2026-08-10T10:00:00',
                'scheduled_window_end' => '2026-08-10T12:00:00',
                'status' => 'zakazano',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'zakazano')
            ->assertJsonPath('data.technician.name', $this->technician->name)
            ->assertJsonPath('notifications_sent.0', 'termin_potvrdjen');

        $termin = NotificationLog::where('template_key', 'termin_potvrdjen')->firstOrFail();
        $this->assertStringContainsString('ponedjeljak 10.08.2026', $termin->body);
        $this->assertStringContainsString('između 10:00 i 12:00', $termin->body);
        $this->assertStringContainsString($this->technician->name, $termin->body);
        $this->assertStringNotContainsString('{', $termin->body);

        // 3. Majstor javlja da je krenuo.
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 15, 0));

        $this->actingAs($this->majstor, 'sanctum')
            ->postJson('/api/v1/technician/jobs/'.$jobId.'/start')
            ->assertOk()
            ->assertJsonPath('data.status', 'u_toku');

        // 4. Majstor zatvara nalog sa nalazom, stavkom, materijalom i slikama.
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 11, 0, 0));

        $stavka = PriceItem::where('name', 'Zamjena baterije (lavabo ili sudopera)')->firstOrFail();

        $this->actingAs($this->majstor, 'sanctum')
            ->postJson('/api/v1/technician/jobs/'.$jobId.'/complete', [
                'findings' => 'Zamijenjena baterija na lavabou, provjereno curenje.',
                'items' => [['price_item_id' => $stavka->id, 'qty' => 1]],
                'materials' => [['name' => 'Baterija za lavabo', 'purchase_price' => 20, 'qty' => 2]],
                'photos_before' => [UploadedFile::fake()->image('prije.jpg')],
                'photos_after' => [UploadedFile::fake()->image('poslije.jpg')],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'zavrseno')
            ->assertJsonPath('data.visit_source', 'izlazak')
            ->assertJsonPath('data.warranty_until', '2027-08-10');

        $job = Job::findOrFail($jobId);

        $this->assertSame(JobStatus::Zavrseno, $job->status);
        $this->assertSame(VisitSource::Izlazak, $job->visit_source);
        $this->assertSame('2026-08-10T11:00:00+00:00', $job->completed_at->toIso8601String());
        $this->assertSame('2027-08-10', $job->warranty_until->toDateString());

        // Faktura: rad je pokriven izlaskom, materijal se naplacuje.
        $invoice = Invoice::where('job_id', $job->id)->firstOrFail();
        $this->assertSame(InvoiceType::Rad, $invoice->type);
        $this->assertSame(0.0, (float) $invoice->labor_total);
        // Nabavna 20, marza 20 posto, popust paketa na materijal 5 posto, 2 komada.
        $this->assertSame(45.60, (float) $invoice->material_total);
        $this->assertSame(45.60, (float) $invoice->total);
        $this->assertSame(InvoiceStatus::Nenaplaceno, $invoice->status);

        // Stavka rada je upisana sa punim iznosom, iako se ne naplacuje.
        $item = $job->items()->firstOrFail();
        $this->assertSame(25, (int) $item->discount_pct);
        $this->assertSame(55.0, (float) $item->base_price);
        $this->assertSame(41.0, (float) $item->line_total);

        // Izlazak je potrosen na adresi.
        $property = SubscriptionProperty::findOrFail($job->subscription_property_id);
        $this->assertSame(2, $property->remaining_visits);

        // Karton doma je dobio red.
        $record = HomeRecord::where('job_id', $job->id)->firstOrFail();
        $this->assertSame('intervencija', $record->type->value);
        $this->assertSame('Vodoinstalacije', $record->title);
        $this->assertStringContainsString('Zamijenjena baterija', (string) $record->body);

        // Fotografije su na public disku.
        $photos = JobPhoto::where('job_id', $job->id)->get();
        $this->assertCount(2, $photos, 'Klijent nije slao sliku, ostaju dvije majstorove.');
        $this->assertSame(1, $photos->where('type', JobPhotoType::Prije)->count());
        $this->assertSame(1, $photos->where('type', JobPhotoType::Poslije)->count());

        foreach ($photos as $photo) {
            Storage::disk('public')->assertExists($photo->path);
        }

        // Cetiri obavjestenja, svako na dva kanala.
        $log = NotificationLog::where('job_id', $job->id)->get();
        $this->assertEqualsCanonicalizing(
            ['prijava_primljena', 'termin_potvrdjen', 'majstor_krenuo', 'zavrseno'],
            $log->pluck('template_key')->unique()->values()->all()
        );
        $this->assertCount(8, $log);

        $zavrseno = $log->firstWhere('template_key', 'zavrseno');
        $this->assertStringContainsString('10.08.2027', $zavrseno->body);
        $this->assertStringNotContainsString('{', $zavrseno->body);

        Mail::assertQueued(IzvjestajMail::class);
    }

    public function test_klijent_vidi_racun_i_garanciju_na_svom_nalogu(): void
    {
        $job = $this->zavrsenNalog();

        $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/jobs/'.$job->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'zavrseno')
            ->assertJsonPath('data.warranty_until', $job->warranty_until->toDateString())
            ->assertJsonPath('data.invoice.labor_total', 0)
            ->assertJsonCount(1, 'data.invoice.materials')
            ->assertJsonCount(2, 'data.photos');
    }

    /**
     * Nalog doveden do zavrsetka, za testove koji krecu poslije toga.
     */
    private function zavrsenNalog(): Job
    {
        $job = Job::create([
            'number' => Job::nextNumber(),
            'user_id' => $this->klijent->id,
            'subscription_id' => $this->klijent->activeSubscription->id,
            'subscription_property_id' => $this->klijent->activeSubscription->properties->first()->id,
            'price_category_id' => PriceCategory::where('slug', 'vodoinstalacije')->value('id'),
            'technician_id' => $this->technician->id,
            'status' => JobStatus::UToku,
            'description' => 'Bojler ne grije vodu.',
            'deadline_at' => Carbon::now()->addHours(48),
        ]);

        $this->actingAs($this->majstor, 'sanctum')
            ->postJson('/api/v1/technician/jobs/'.$job->id.'/complete', [
                'findings' => 'Zamijenjen grijač u bojleru i provjeren termostat.',
                'materials' => [['name' => 'Grijač 2 kW', 'purchase_price' => 40, 'qty' => 1]],
                'photos_before' => [UploadedFile::fake()->image('prije.jpg')],
                'photos_after' => [UploadedFile::fake()->image('poslije.jpg')],
            ])
            ->assertOk();

        return $job->refresh();
    }
}
