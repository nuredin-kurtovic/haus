<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Job;
use App\Models\NotificationLog;
use App\Models\PriceCategory;
use App\Models\Technician;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminJobTest extends TestCase
{
    use RefreshDatabase;

    private User $dispecer;

    private User $klijent;

    private Technician $damir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();
        Storage::fake('public');

        // Petak. Ponedjeljak 10.08. je radni dan, nedjelja je 09.08.
        Carbon::setTestNow(Carbon::create(2026, 8, 7, 10, 0, 0));

        $this->dispecer = User::where('email', 'dispecer@haus.ba')->firstOrFail();
        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $this->damir = User::where('email', 'damir@haus.ba')->firstOrFail()->technician;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_lista_nosi_brojace_po_stanju(): void
    {
        $this->nalog();
        $this->nalog(['status' => JobStatus::Zakazano]);
        $this->nalog(['status' => JobStatus::Zavrseno]);

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/jobs')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.counts.novo', 1)
            ->assertJsonPath('meta.counts.zakazano', 1)
            ->assertJsonPath('meta.counts.zavrseno', 1)
            ->assertJsonPath('meta.counts.ukupno', 3);
    }

    public function test_lista_se_filtrira_po_stanju_i_pretrazuje(): void
    {
        $novi = $this->nalog();
        $this->nalog(['status' => JobStatus::Zakazano, 'description' => 'Klima ne hladi vise od sedmicu.']);

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/jobs?status=novo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $novi->id);

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/jobs?q=Klima')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.counts.zakazano', 1);

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/jobs?q=Amina')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_detalj_nosi_klijenta_pretplatu_i_historiju_obavjestenja(): void
    {
        $job = $this->nalog();

        $this->zakazi($job)->assertOk();

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/jobs/'.$job->id)
            ->assertOk()
            ->assertJsonPath('data.client.email', 'klijent@haus.ba')
            ->assertJsonPath('data.subscription.package', 'HAUS Plus')
            ->assertJsonPath('data.subscription.remaining_visits', 3)
            ->assertJsonCount(2, 'data.notifications')
            ->assertJsonPath('data.notifications.0.template_key', 'termin_potvrdjen');
    }

    public function test_zakazivanje_trazi_majstora_i_prozor(): void
    {
        $job = $this->nalog();

        $this->actingAs($this->dispecer, 'sanctum')
            ->patchJson('/api/v1/admin/jobs/'.$job->id, ['status' => 'zakazano'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('technician_id');

        $this->actingAs($this->dispecer, 'sanctum')
            ->patchJson('/api/v1/admin/jobs/'.$job->id, [
                'status' => 'zakazano',
                'technician_id' => $this->damir->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_window_start');
    }

    public function test_prozor_mora_trajati_tacno_dva_sata(): void
    {
        $job = $this->nalog();

        $response = $this->zakazi($job, '2026-08-10T10:00:00', '2026-08-10T13:00:00')
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_window_start');

        $this->assertSame(
            'Prozor izlaska mora trajati tačno 2 sata.',
            $response->json('errors.scheduled_window_start.0')
        );
    }

    public function test_termin_izvan_radnog_vremena_se_odbija(): void
    {
        $job = $this->nalog();

        $this->zakazi($job, '2026-08-10T17:00:00', '2026-08-10T19:00:00')
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_window_start');

        // Subota, radimo do 14:00.
        $this->zakazi($job, '2026-08-08T13:00:00', '2026-08-08T15:00:00')
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_window_start');
    }

    public function test_nedjelja_prolazi_samo_za_hitan_nalog(): void
    {
        $redovan = $this->nalog();

        $response = $this->zakazi($redovan, '2026-08-09T10:00:00', '2026-08-09T12:00:00')
            ->assertStatus(422);

        $this->assertSame(
            'Nedjeljom radimo samo hitne intervencije. Odaberite drugi dan.',
            $response->json('errors.scheduled_window_start.0')
        );

        $hitan = $this->nalog(['is_emergency' => true]);

        $this->zakazi($hitan, '2026-08-09T10:00:00', '2026-08-09T12:00:00')->assertOk();
    }

    public function test_novi_termin_na_zakazanom_nalogu_ponovo_javlja_klijentu(): void
    {
        $job = $this->nalog();

        $this->zakazi($job)->assertOk();

        $this->actingAs($this->dispecer, 'sanctum')
            ->patchJson('/api/v1/admin/jobs/'.$job->id, [
                'scheduled_window_start' => '2026-08-10T14:00:00',
                'scheduled_window_end' => '2026-08-10T16:00:00',
            ])
            ->assertOk()
            ->assertJsonPath('notifications_sent.0', 'termin_potvrdjen');

        $tekstovi = NotificationLog::where('template_key', 'termin_potvrdjen')
            ->where('channel', 'mejl')
            ->pluck('body');

        $this->assertCount(2, $tekstovi);
        $this->assertStringContainsString('između 14:00 i 16:00', $tekstovi->last());
    }

    public function test_dispecer_moze_pokrenuti_izlazak_umjesto_majstora(): void
    {
        $job = $this->nalog();
        $this->zakazi($job)->assertOk();

        $this->actingAs($this->dispecer, 'sanctum')
            ->patchJson('/api/v1/admin/jobs/'.$job->id, ['status' => 'u_toku'])
            ->assertOk()
            ->assertJsonPath('data.status', 'u_toku')
            ->assertJsonPath('notifications_sent.0', 'majstor_krenuo');

        $this->assertSame(2, NotificationLog::where('template_key', 'majstor_krenuo')->count());
    }

    public function test_zavrsetak_ne_ide_kroz_izmjenu_naloga(): void
    {
        $job = $this->nalog(['status' => JobStatus::UToku]);

        $this->actingAs($this->dispecer, 'sanctum')
            ->patchJson('/api/v1/admin/jobs/'.$job->id, ['status' => 'zavrseno'])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Nalog se zatvara nalazom. Koristite završetak naloga.');
    }

    public function test_pokretanje_izlaska_na_novom_nalogu_se_odbija(): void
    {
        $job = $this->nalog();

        $this->actingAs($this->dispecer, 'sanctum')
            ->patchJson('/api/v1/admin/jobs/'.$job->id, ['status' => 'u_toku'])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Izlazak se pokreće samo na zakazanom nalogu.');
    }

    public function test_pregled_obavjestenja_vraca_tacan_tekst_bez_slanja(): void
    {
        $job = $this->nalog();

        $response = $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/jobs/'.$job->id.'/notification-preview?'.http_build_query([
                'status' => 'zakazano',
                'technician_id' => $this->damir->id,
                'scheduled_window_start' => '2026-08-10T10:00:00',
                'scheduled_window_end' => '2026-08-10T12:00:00',
            ]))
            ->assertOk()
            ->assertJsonPath('data.template_key', 'termin_potvrdjen');

        $this->assertSame(
            'HAUS: Termin potvrđen. ponedjeljak 10.08.2026, između 10:00 i 12:00. Majstor: Damir Hodžić. Otkazivanje u aplikaciji.',
            $response->json('data.body')
        );

        // Pregled ne salje nista i ne mijenja nalog.
        $this->assertSame(0, NotificationLog::count());
        $this->assertSame(JobStatus::Novo, $job->refresh()->status);

        // Tekst je isti onaj koji tranzicija zaista posalje.
        $this->zakazi($job)->assertOk();

        $this->assertSame(
            $response->json('data.body'),
            NotificationLog::where('template_key', 'termin_potvrdjen')->value('body')
        );
    }

    public function test_pregled_za_stanje_bez_obavjestenja_vraca_422(): void
    {
        $job = $this->nalog();

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/jobs/'.$job->id.'/notification-preview?status=novo')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Za to stanje nema obavještenja klijentu.');
    }

    public function test_garancijski_nalog_se_otvara_na_zavrsen_nalog(): void
    {
        $job = $this->nalog(['status' => JobStatus::Zavrseno, 'completed_at' => Carbon::now()]);

        $response = $this->actingAs($this->dispecer, 'sanctum')
            ->postJson('/api/v1/admin/jobs/'.$job->id.'/warranty-job')
            ->assertCreated()
            ->assertJsonPath('data.type', 'garancija');

        $garancija = Job::findOrFail($response->json('data.id'));

        $this->assertSame($job->id, $garancija->parent_job_id);
        $this->assertSame($job->price_category_id, $garancija->price_category_id);
        $this->assertSame($job->subscription_property_id, $garancija->subscription_property_id);
        $this->assertSame(JobType::Garancija, $garancija->type);
        $this->assertSame(JobStatus::Novo, $garancija->status);
        // HAUS Plus, redovan rok: 48 sati.
        $this->assertSame('2026-08-09T10:00:00+00:00', $garancija->deadline_at->toIso8601String());

        $this->assertSame(2, NotificationLog::where('template_key', 'prijava_primljena')->count());
    }

    public function test_garancijski_nalog_na_nezavrsen_nalog_se_odbija(): void
    {
        $job = $this->nalog();

        $this->actingAs($this->dispecer, 'sanctum')
            ->postJson('/api/v1/admin/jobs/'.$job->id.'/warranty-job')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Garancijski nalog se otvara samo na završen nalog.');
    }

    public function test_dispecer_zatvara_nalog_u_ime_majstora(): void
    {
        $job = $this->nalog(['status' => JobStatus::UToku]);

        $this->actingAs($this->dispecer, 'sanctum')
            ->postJson('/api/v1/admin/jobs/'.$job->id.'/complete', [
                'findings' => 'Zamijenjen ventil pod sudoperom.',
                'photos_before' => [UploadedFile::fake()->image('prije.jpg')],
                'photos_after' => [UploadedFile::fake()->image('poslije.jpg')],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'zavrseno')
            ->assertJsonPath('data.visit_source', 'izlazak')
            ->assertJsonPath('data.invoice.total', 0);

        $this->assertSame('zavrseno', $job->refresh()->status->value);
    }

    public function test_nepoznat_nalog_vraca_404(): void
    {
        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/jobs/999999')
            ->assertStatus(404);
    }

    private function zakazi(Job $job, string $od = '2026-08-10T10:00:00', string $do = '2026-08-10T12:00:00'): TestResponse
    {
        return $this->actingAs($this->dispecer, 'sanctum')
            ->patchJson('/api/v1/admin/jobs/'.$job->id, [
                'technician_id' => $this->damir->id,
                'scheduled_window_start' => $od,
                'scheduled_window_end' => $do,
                'status' => 'zakazano',
            ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function nalog(array $overrides = []): Job
    {
        $subscription = $this->klijent->activeSubscription;

        return Job::create(array_merge([
            'number' => Job::nextNumber(),
            'user_id' => $this->klijent->id,
            'subscription_id' => $subscription->id,
            'subscription_property_id' => $subscription->properties->first()->id,
            'price_category_id' => PriceCategory::where('slug', 'vodoinstalacije')->value('id'),
            'status' => JobStatus::Novo,
            'description' => 'Slavina u kupatilu curi cijeli dan.',
            'deadline_at' => Carbon::now()->addHours(48),
        ], $overrides));
    }
}
