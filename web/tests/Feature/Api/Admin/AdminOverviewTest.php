<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\HomeRecordType;
use App\Enums\JobStatus;
use App\Models\HomeRecord;
use App\Models\Job;
use App\Models\PriceCategory;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $dispecer;

    private User $klijent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        Carbon::setTestNow(Carbon::create(2026, 8, 10, 9, 0, 0));

        $this->dispecer = User::where('email', 'dispecer@haus.ba')->firstOrFail();
        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_nosi_kpi_rokove_raspored_i_obnove(): void
    {
        // Rok danas, jos nije zavrsen.
        $this->nalog(['deadline_at' => Carbon::now()->addHours(3)]);

        // Zakazan danas, u prozoru od 10 do 12.
        $this->nalog([
            'status' => JobStatus::Zakazano,
            'technician_id' => User::where('email', 'damir@haus.ba')->firstOrFail()->technician->id,
            'scheduled_window_start' => Carbon::create(2026, 8, 10, 10, 0),
            'scheduled_window_end' => Carbon::create(2026, 8, 10, 12, 0),
            'deadline_at' => Carbon::now()->addDays(2),
        ]);

        // Zavrsen prije 5 sati, ulazi u prosjek.
        $this->nalog([
            'status' => JobStatus::Zavrseno,
            'deadline_at' => Carbon::now()->addDays(2),
            'completed_at' => Carbon::now(),
            'created_at' => Carbon::now()->subHours(5),
        ]);

        // Pretplata koja istice za 30 dana ulazi u obnove.
        Subscription::where('user_id', $this->klijent->id)
            ->update(['ends_at' => Carbon::now()->addDays(30)]);

        $response = $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('kpi.novi_danas', 3)
            ->assertJsonPath('kpi.aktivni_nalozi', 2)
            ->assertJsonPath('kpi.rokovi_danas', 1)
            ->assertJsonPath('kpi.aktivne_pretplate', 2)
            ->assertJsonCount(1, 'deadlines_today')
            ->assertJsonCount(1, 'schedule_today');

        $this->assertSame(5.0, (float) $response->json('kpi.prosjek_zavrsetka_h'));
        $this->assertSame('10:00 do 12:00', $response->json('schedule_today.0.window'));
        $this->assertSame(1, count($response->json('schedule_today.0.jobs')));

        $obnove = $response->json('renewals_soon');
        $this->assertCount(1, $obnove);
        $this->assertSame('klijent@haus.ba', $obnove[0]['client']['email']);
        $this->assertSame(30, $obnove[0]['dana_do_isteka']);
    }

    public function test_lista_klijenata_i_pretraga(): void
    {
        $response = $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/clients')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame('Amina Kovačević', $response->json('data.0.name'));
        $this->assertSame('HAUS Plus', $response->json('data.0.package'));
        $this->assertSame(1, $response->json('data.0.properties_count'));

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/clients?q=Vedad')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'pro@haus.ba')
            ->assertJsonPath('data.0.properties_count', 3);

        // Pretraga radi i po ulici.
        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/clients?'.http_build_query(['q' => 'Grbavička']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'klijent@haus.ba');
    }

    public function test_karton_klijenta_nosi_naloge_i_hronologiju(): void
    {
        $job = $this->nalog();

        HomeRecord::create([
            'subscription_property_id' => $job->subscription_property_id,
            'job_id' => $job->id,
            'type' => HomeRecordType::Intervencija,
            'title' => 'Vodoinstalacije',
            'body' => 'Zamijenjena baterija na lavabou.',
            'recorded_at' => Carbon::now(),
        ]);

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/clients/'.$this->klijent->id)
            ->assertOk()
            ->assertJsonPath('data.client.email', 'klijent@haus.ba')
            ->assertJsonCount(1, 'data.subscriptions')
            ->assertJsonCount(1, 'data.properties')
            ->assertJsonCount(1, 'data.jobs')
            ->assertJsonCount(1, 'data.home_records')
            ->assertJsonPath('data.home_records.0.title', 'Vodoinstalacije')
            ->assertJsonPath('data.home_records.0.job_number', $job->number)
            ->assertJsonPath('data.properties.0.remaining_visits', 3);
    }

    public function test_nepoznat_klijent_vraca_404(): void
    {
        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/clients/999999')
            ->assertStatus(404);
    }

    public function test_pretplate_prikazuju_iskoristenost(): void
    {
        $stan = $this->klijent->activeSubscription->properties->first();
        $stan->update(['remaining_visits' => 1]);

        $response = $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $plus = collect($response->json('data'))->firstWhere('client.email', 'klijent@haus.ba');

        $this->assertSame('HAUS Plus', $plus['package']['name']);
        $this->assertSame(3, $plus['usage']['visits_total']);
        $this->assertSame(1, $plus['usage']['visits_remaining']);
        $this->assertSame(2, $plus['usage']['visits_used']);
        $this->assertTrue($plus['auto_renew']);

        $pro = collect($response->json('data'))->firstWhere('client.email', 'pro@haus.ba');

        // HAUS Pro, 3 stana po 5 izlazaka.
        $this->assertSame(15, $pro['usage']['visits_total']);
        $this->assertSame(15, $pro['usage']['visits_remaining']);
        $this->assertSame(3, $pro['properties_count']);
    }

    public function test_pretplate_se_filtriraju_po_stanju(): void
    {
        Subscription::where('user_id', $this->klijent->id)->update(['status' => 'otkazana']);

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions?status=aktivna')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client.email', 'pro@haus.ba');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function nalog(array $overrides = []): Job
    {
        $subscription = $this->klijent->activeSubscription;

        $job = Job::create(array_merge([
            'number' => Job::nextNumber(),
            'user_id' => $this->klijent->id,
            'subscription_id' => $subscription->id,
            'subscription_property_id' => $subscription->properties->first()->id,
            'price_category_id' => PriceCategory::where('slug', 'vodoinstalacije')->value('id'),
            'status' => JobStatus::Novo,
            'description' => 'Slavina u kupatilu curi cijeli dan.',
            'deadline_at' => Carbon::now()->addHours(48),
        ], $overrides));

        if (isset($overrides['created_at'])) {
            $job->forceFill(['created_at' => $overrides['created_at']])->save();
        }

        return $job;
    }
}
