<?php

namespace Tests\Feature\Api\Client;

use App\Enums\JobStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Job;
use App\Models\PriceCategory;
use App\Models\Subscription;
use App\Models\Technician;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $klijent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
    }

    private function prijavi(string $opis = 'Slavina u kupatilu curi cijeli dan.'): Job
    {
        $this->actingAs($this->klijent, 'sanctum')
            ->postJson('/api/v1/client/jobs', [
                'price_category_id' => PriceCategory::where('slug', 'vodoinstalacije')->value('id'),
                'description' => $opis,
                'is_emergency' => false,
            ])->assertCreated();

        return Job::query()->latest('id')->firstOrFail();
    }

    public function test_dashboard_bez_naloga_vraca_pretplatu_i_prazno(): void
    {
        $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonPath('subscription.package.name', 'HAUS Plus')
            ->assertJsonPath('subscription.package.slug', 'haus-plus')
            ->assertJsonPath('subscription.status', 'aktivna')
            ->assertJsonPath('subscription.remaining_visits', 3)
            ->assertJsonPath('subscription.remaining_inspections', 1)
            ->assertJsonPath('subscription.free_interventions', 0)
            ->assertJsonPath('active_job', null)
            ->assertJsonCount(0, 'recent_jobs');
    }

    public function test_dashboard_prikazuje_nalog_u_toku_sa_koracima(): void
    {
        $job = $this->prijavi();

        $response = $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonPath('active_job.id', $job->id)
            ->assertJsonPath('active_job.number', $job->number)
            ->assertJsonPath('active_job.status', 'novo')
            ->assertJsonPath('active_job.category', 'Vodoinstalacije')
            ->assertJsonCount(4, 'active_job.steps')
            ->assertJsonCount(1, 'recent_jobs');

        $steps = collect($response->json('active_job.steps'))->keyBy('key');

        $this->assertSame(
            ['prijava_primljena', 'majstor_dodijeljen', 'termin_potvrdjen', 'majstor_krenuo'],
            collect($response->json('active_job.steps'))->pluck('key')->all()
        );
        $this->assertTrue($steps['prijava_primljena']['done']);
        $this->assertFalse($steps['majstor_dodijeljen']['done']);
        $this->assertFalse($steps['termin_potvrdjen']['done']);
        $this->assertFalse($steps['majstor_krenuo']['done']);
        $this->assertNotEmpty($steps['prijava_primljena']['label']);
    }

    public function test_koraci_prate_dodjelu_termin_i_polazak(): void
    {
        $job = $this->prijavi();

        $job->update(['technician_id' => Technician::query()->value('id')]);
        $this->assertTrue($this->korak('majstor_dodijeljen'));
        $this->assertFalse($this->korak('termin_potvrdjen'));

        $job->update([
            'status' => JobStatus::Zakazano,
            'scheduled_window_start' => Carbon::now()->addDay(),
            'scheduled_window_end' => Carbon::now()->addDay()->addHours(2),
        ]);
        $this->assertTrue($this->korak('termin_potvrdjen'));
        $this->assertFalse($this->korak('majstor_krenuo'));

        $job->update(['status' => JobStatus::UToku]);
        $this->assertTrue($this->korak('majstor_krenuo'));
    }

    public function test_zavrsen_nalog_nije_aktivan_ali_ostaje_u_zadnjima(): void
    {
        $job = $this->prijavi();
        $job->update(['status' => JobStatus::Zavrseno, 'completed_at' => Carbon::now()]);

        $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonPath('active_job', null)
            ->assertJsonCount(1, 'recent_jobs')
            ->assertJsonPath('recent_jobs.0.id', $job->id)
            ->assertJsonPath('recent_jobs.0.status', 'zavrseno')
            ->assertJsonPath('recent_jobs.0.type', 'redovno')
            ->assertJsonPath('recent_jobs.0.category', 'Vodoinstalacije');
    }

    public function test_zadnji_nalozi_su_najvise_pet_i_najnoviji_prvi(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->prijavi('Kvar broj '.$i.' na instalaciji u stanu.');
        }

        $response = $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonCount(5, 'recent_jobs');

        $this->assertSame('HAUS-'.now()->year.'-0007', $response->json('recent_jobs.0.number'));
        $this->assertSame('HAUS-'.now()->year.'-0003', $response->json('recent_jobs.4.number'));
    }

    public function test_klijent_koji_ceka_uplatu_i_dalje_vidi_svoje_stanje(): void
    {
        Subscription::where('user_id', $this->klijent->id)
            ->update(['status' => SubscriptionStatus::CekanjeUplate, 'starts_at' => null, 'ends_at' => null]);

        $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonPath('subscription.status', 'cekanje_uplate')
            ->assertJsonPath('subscription.ends_at', null)
            ->assertJsonPath('active_job', null);

        // Pregled pretplate i profila radi i prije nego uplata legne.
        $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/subscription')
            ->assertOk()
            ->assertJsonPath('status', 'cekanje_uplate');

        $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/profile')
            ->assertOk()
            ->assertJsonPath('data.email', 'klijent@haus.ba');
    }

    private function korak(string $key): bool
    {
        $steps = $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->json('active_job.steps');

        return (bool) collect($steps)->firstWhere('key', $key)['done'];
    }
}
