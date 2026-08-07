<?php

namespace Tests\Feature\Api\Technician;

use App\Enums\JobStatus;
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
use Tests\TestCase;

class TechnicianJobTest extends TestCase
{
    use RefreshDatabase;

    private User $klijent;

    private User $damir;

    private User $emir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();
        Storage::fake('public');

        Carbon::setTestNow(Carbon::create(2026, 8, 10, 9, 0, 0));

        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $this->damir = User::where('email', 'damir@haus.ba')->firstOrFail();
        $this->emir = User::where('email', 'emir@haus.ba')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_majstor_vidi_samo_svoje_naloge(): void
    {
        $moj = $this->nalog($this->damir->technician);
        $tudji = $this->nalog($this->emir->technician);

        $response = $this->actingAs($this->damir, 'sanctum')
            ->getJson('/api/v1/technician/jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($moj->id, $response->json('data.0.id'));
        $this->assertSame('Amina Kovačević', $response->json('data.0.client.name'));
        $this->assertSame('Sarajevo', $response->json('data.0.address.city'));

        $this->actingAs($this->damir, 'sanctum')
            ->getJson('/api/v1/technician/jobs/'.$tudji->id)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Nalog nije pronađen.');
    }

    public function test_lista_se_filtrira_po_stanju_i_danu(): void
    {
        $this->nalog($this->damir->technician, [
            'scheduled_window_start' => Carbon::create(2026, 8, 10, 10, 0),
            'scheduled_window_end' => Carbon::create(2026, 8, 10, 12, 0),
            'status' => JobStatus::Zakazano,
        ]);

        $this->nalog($this->damir->technician, [
            'scheduled_window_start' => Carbon::create(2026, 8, 11, 10, 0),
            'scheduled_window_end' => Carbon::create(2026, 8, 11, 12, 0),
            'status' => JobStatus::Zakazano,
        ]);

        $this->actingAs($this->damir, 'sanctum')
            ->getJson('/api/v1/technician/jobs?date=2026-08-10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->damir, 'sanctum')
            ->getJson('/api/v1/technician/jobs?status=novo')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_detalj_nosi_napomenu_o_pristupu_i_preostale_izlaske(): void
    {
        $job = $this->nalog($this->damir->technician);

        $job->property->update([
            'contact_name' => 'Lejla Musić',
            'contact_note' => 'Ključ kod komšinice.',
        ]);

        $this->actingAs($this->damir, 'sanctum')
            ->getJson('/api/v1/technician/jobs/'.$job->id)
            ->assertOk()
            ->assertJsonPath('data.contact.name', 'Lejla Musić')
            ->assertJsonPath('data.contact.note', 'Ključ kod komšinice.')
            ->assertJsonPath('data.entitlements.remaining_visits', 3)
            ->assertJsonPath('data.entitlements.ide_na_naplatu', false);
    }

    public function test_detalj_javlja_kad_izlazak_ide_na_naplatu(): void
    {
        $job = $this->nalog($this->damir->technician);
        $job->property->update(['remaining_visits' => 0]);

        $this->actingAs($this->damir, 'sanctum')
            ->getJson('/api/v1/technician/jobs/'.$job->id)
            ->assertOk()
            ->assertJsonPath('data.entitlements.ide_na_naplatu', true);
    }

    public function test_start_prebacuje_u_toku_i_javlja_klijentu(): void
    {
        $job = $this->nalog($this->damir->technician, [
            'status' => JobStatus::Zakazano,
            'scheduled_window_start' => Carbon::create(2026, 8, 10, 10, 0),
            'scheduled_window_end' => Carbon::create(2026, 8, 10, 12, 0),
        ]);

        $this->actingAs($this->damir, 'sanctum')
            ->postJson('/api/v1/technician/jobs/'.$job->id.'/start')
            ->assertOk()
            ->assertJsonPath('data.status', 'u_toku');

        $log = NotificationLog::where('template_key', 'majstor_krenuo')->get();

        $this->assertCount(2, $log);
        $this->assertStringContainsString('Damir Hodžić', $log->first()->body);
        $this->assertStringContainsString('do 12:00', $log->first()->body);
        $this->assertStringNotContainsString('{', $log->first()->body);
    }

    public function test_ponovljen_start_ne_salje_obavjestenje_ponovo(): void
    {
        $job = $this->nalog($this->damir->technician, [
            'status' => JobStatus::Zakazano,
            'scheduled_window_start' => Carbon::create(2026, 8, 10, 10, 0),
            'scheduled_window_end' => Carbon::create(2026, 8, 10, 12, 0),
        ]);

        $this->actingAs($this->damir, 'sanctum')->postJson('/api/v1/technician/jobs/'.$job->id.'/start')->assertOk();
        $this->actingAs($this->damir, 'sanctum')->postJson('/api/v1/technician/jobs/'.$job->id.'/start')->assertOk();

        $this->assertSame(2, NotificationLog::where('template_key', 'majstor_krenuo')->count());
    }

    public function test_start_na_novom_nalogu_se_odbija(): void
    {
        $job = $this->nalog($this->damir->technician);

        $this->actingAs($this->damir, 'sanctum')
            ->postJson('/api/v1/technician/jobs/'.$job->id.'/start')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Izlazak se pokreće samo na zakazanom nalogu.');
    }

    public function test_cjenovnik_je_bez_cijena_po_paketima(): void
    {
        $response = $this->actingAs($this->damir, 'sanctum')
            ->getJson('/api/v1/technician/price-list')
            ->assertOk();

        $this->assertSame(6, count($response->json('data')));
        $this->assertArrayHasKey('base_price', $response->json('data.0.items.0'));
        $this->assertArrayNotHasKey('prices', $response->json('data.0.items.0'));
        $this->assertSame(1, $response->json('meta.price_list_version'));
    }

    public function test_zatvaranje_trazi_obje_fotografije(): void
    {
        $job = $this->nalog($this->damir->technician, ['status' => JobStatus::UToku]);

        $this->actingAs($this->damir, 'sanctum')
            ->postJson('/api/v1/technician/jobs/'.$job->id.'/complete', [
                'findings' => 'Zamijenjena baterija na lavabou.',
                'photos_before' => [UploadedFile::fake()->image('prije.jpg')],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photos_after');
    }

    public function test_zatvaranje_tudjeg_naloga_vraca_404(): void
    {
        $tudji = $this->nalog($this->emir->technician, ['status' => JobStatus::UToku]);

        $this->actingAs($this->damir, 'sanctum')
            ->postJson('/api/v1/technician/jobs/'.$tudji->id.'/complete', [
                'findings' => 'Zamijenjena baterija na lavabou.',
                'photos_before' => [UploadedFile::fake()->image('prije.jpg')],
                'photos_after' => [UploadedFile::fake()->image('poslije.jpg')],
            ])
            ->assertStatus(404);
    }

    public function test_klijent_ne_ulazi_u_serviserski_dio(): void
    {
        $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/technician/jobs')
            ->assertStatus(403);
    }

    public function test_majstor_bez_veze_na_servisera_ne_prolazi(): void
    {
        $bezVeze = User::factory()->create();
        $bezVeze->syncRoles(['majstor']);

        $this->actingAs($bezVeze, 'sanctum')
            ->getJson('/api/v1/technician/jobs')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Vaš nalog nije povezan sa serviserom. Javite se dispečeru.');
    }

    public function test_iskljucen_majstor_ne_prolazi(): void
    {
        $this->damir->technician->update(['active' => false]);

        $this->actingAs($this->damir, 'sanctum')
            ->getJson('/api/v1/technician/jobs')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Vaš serviserski nalog je isključen. Javite se dispečeru.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function nalog(Technician $technician, array $overrides = []): Job
    {
        $subscription = $this->klijent->activeSubscription;

        return Job::create(array_merge([
            'number' => Job::nextNumber(),
            'user_id' => $this->klijent->id,
            'subscription_id' => $subscription->id,
            'subscription_property_id' => $subscription->properties->first()->id,
            'price_category_id' => PriceCategory::where('slug', 'vodoinstalacije')->value('id'),
            'technician_id' => $technician->id,
            'status' => JobStatus::Novo,
            'description' => 'Slavina u kupatilu curi cijeli dan.',
            'deadline_at' => Carbon::now()->addHours(48),
        ], $overrides));
    }
}
