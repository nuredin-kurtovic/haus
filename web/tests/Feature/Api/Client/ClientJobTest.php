<?php

namespace Tests\Feature\Api\Client;

use App\Enums\JobPhotoType;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\SubscriptionStatus;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\NotificationLog;
use App\Models\Package;
use App\Models\PriceCategory;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
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

class ClientJobTest extends TestCase
{
    use RefreshDatabase;

    private User $klijent;

    private User $pro;

    private PriceCategory $vodoinstalacije;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();
        Storage::fake('public');

        // Vrijeme se zamrzava da rok bude provjerljiv na minutu.
        Carbon::setTestNow(Carbon::create(2026, 8, 7, 10, 0, 0));

        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $this->pro = User::where('email', 'pro@haus.ba')->firstOrFail();
        $this->vodoinstalacije = PriceCategory::where('slug', 'vodoinstalacije')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function prijava(?User $user = null, array $overrides = []): TestResponse
    {
        $payload = array_merge([
            'price_category_id' => $this->vodoinstalacije->id,
            'description' => 'Slavina u kupatilu curi cijeli dan.',
            'is_emergency' => false,
        ], $overrides);

        return $this->actingAs($user ?? $this->klijent, 'sanctum')
            ->postJson('/api/v1/client/jobs', $payload);
    }

    public function test_prijava_kvara_pravi_nalog_sa_rokom_iz_paketa(): void
    {
        $response = $this->prijava()->assertCreated();

        $job = Job::query()->latest('id')->firstOrFail();

        $response
            ->assertJsonPath('job.id', $job->id)
            ->assertJsonPath('job.number', $job->number);

        // HAUS Plus, redovno: 48 sati od prijave.
        $this->assertSame(
            Carbon::create(2026, 8, 9, 10, 0, 0)->toIso8601String(),
            $job->deadline_at->toIso8601String()
        );
        $this->assertSame($response->json('job.deadline_at'), $job->deadline_at->toIso8601String());

        $this->assertSame(JobStatus::Novo, $job->status);
        $this->assertSame(JobType::Redovno, $job->type);
        $this->assertSame($this->klijent->id, $job->user_id);
        $this->assertFalse($job->is_emergency);
        $this->assertNull($job->technician_id);
    }

    public function test_hitna_prijava_ima_kraci_rok(): void
    {
        $this->prijava(overrides: ['is_emergency' => true])->assertCreated();

        $job = Job::query()->latest('id')->firstOrFail();

        // HAUS Plus, hitno: 12 sati.
        $this->assertTrue($job->is_emergency);
        $this->assertSame(
            Carbon::create(2026, 8, 7, 22, 0, 0)->toIso8601String(),
            $job->deadline_at->toIso8601String()
        );
    }

    public function test_hitnost_stize_i_kao_tekst_iz_multiparta(): void
    {
        $this->prijava(overrides: ['is_emergency' => 'true'])->assertCreated();

        $this->assertTrue(Job::query()->latest('id')->firstOrFail()->is_emergency);
    }

    public function test_broj_naloga_ide_po_godini_u_nizu(): void
    {
        $this->prijava()->assertCreated();
        $this->prijava()->assertCreated();

        $brojevi = Job::query()->orderBy('id')->pluck('number')->all();

        $this->assertSame(['HAUS-2026-0001', 'HAUS-2026-0002'], $brojevi);
    }

    public function test_sekvenca_broja_krece_ispocetka_u_novoj_godini(): void
    {
        $this->prijava()->assertCreated();

        Carbon::setTestNow(Carbon::create(2027, 1, 3, 9, 0, 0));

        $this->prijava()->assertCreated();

        $this->assertSame(
            ['HAUS-2026-0001', 'HAUS-2027-0001'],
            Job::query()->orderBy('id')->pluck('number')->all()
        );
    }

    public function test_fotografija_se_sprema_kao_tip_prije(): void
    {
        $this->prijava(overrides: [
            'photo' => UploadedFile::fake()->image('kvar.jpg', 800, 600),
        ])->assertCreated();

        $job = Job::query()->latest('id')->firstOrFail();

        /** @var JobPhoto $photo */
        $photo = JobPhoto::where('job_id', $job->id)->firstOrFail();

        $this->assertSame(JobPhotoType::Prije, $photo->type);
        $this->assertStringStartsWith('jobs/'.$job->id.'/', $photo->path);
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_prevelika_fotografija_se_odbija(): void
    {
        $this->prijava(overrides: [
            'photo' => UploadedFile::fake()->image('velika.jpg')->size(9000),
        ])->assertStatus(422)->assertJsonValidationErrors('photo');
    }

    public function test_prijava_upisuje_obavjestenje_sa_brojem_i_rokom(): void
    {
        $this->prijava()->assertCreated();

        $job = Job::query()->latest('id')->firstOrFail();

        $log = NotificationLog::where('template_key', 'prijava_primljena')->get();

        $this->assertCount(2, $log, 'Ocekujemo red za mejl i red za push.');
        $this->assertEqualsCanonicalizing(['mejl', 'push'], $log->pluck('channel')->map->value->all());
        $this->assertSame($job->id, $log->first()->job_id);
        $this->assertStringContainsString($job->number, $log->first()->body);
        $this->assertStringContainsString('09.08.2026. do 10:00', $log->first()->body);
        $this->assertStringNotContainsString('{', $log->first()->body);
    }

    public function test_pretplata_koja_ceka_uplatu_ne_moze_prijaviti_kvar(): void
    {
        Subscription::where('user_id', $this->klijent->id)
            ->update(['status' => SubscriptionStatus::CekanjeUplate]);

        $this->prijava()
            ->assertStatus(403)
            ->assertJsonPath('message', 'Vaša pretplata još nije aktivna. Prijava kvara je moguća čim uplata legne.')
            ->assertJsonPath('subscription_status', 'cekanje_uplate');

        $this->assertSame(0, Job::count());
    }

    public function test_istekla_pretplata_dobija_svoju_poruku(): void
    {
        Subscription::where('user_id', $this->klijent->id)
            ->update(['status' => SubscriptionStatus::Istekla]);

        $this->prijava()
            ->assertStatus(403)
            ->assertJsonPath('message', 'Vaša pretplata je istekla. Obnovite je da biste prijavili kvar.');
    }

    public function test_nalog_se_prima_i_kad_nema_preostalih_izlazaka(): void
    {
        SubscriptionProperty::query()
            ->whereIn('subscription_id', Subscription::where('user_id', $this->klijent->id)->pluck('id'))
            ->update(['remaining_visits' => 0]);

        $this->prijava()->assertCreated();

        $this->assertSame(1, Job::count());
        // Naplata se odlucuje pri zavrsetku, prijava ne dira brojac izlazaka.
        $this->assertSame(0, (int) SubscriptionProperty::query()
            ->whereIn('subscription_id', Subscription::where('user_id', $this->klijent->id)->pluck('id'))
            ->value('remaining_visits'));
    }

    public function test_jedna_adresa_se_uzima_sama(): void
    {
        $this->prijava()->assertCreated();

        $property = SubscriptionProperty::query()
            ->whereIn('subscription_id', Subscription::where('user_id', $this->klijent->id)->pluck('id'))
            ->firstOrFail();

        $this->assertSame($property->id, Job::query()->latest('id')->firstOrFail()->subscription_property_id);
    }

    public function test_pro_sa_vise_stanova_mora_odabrati_adresu(): void
    {
        $this->prijava($this->pro)
            ->assertStatus(422)
            ->assertJsonValidationErrors('subscription_property_id');

        $this->assertSame(0, Job::count());
    }

    public function test_pro_prijavljuje_kvar_na_odabranom_stanu(): void
    {
        $stan = SubscriptionProperty::query()
            ->whereIn('subscription_id', Subscription::where('user_id', $this->pro->id)->pluck('id'))
            ->orderByDesc('id')
            ->firstOrFail();

        $this->prijava($this->pro, ['subscription_property_id' => $stan->id])->assertCreated();

        $job = Job::query()->latest('id')->firstOrFail();

        $this->assertSame($stan->id, $job->subscription_property_id);
        // HAUS Pro, redovno: 24 sata.
        $this->assertSame(
            Carbon::create(2026, 8, 8, 10, 0, 0)->toIso8601String(),
            $job->deadline_at->toIso8601String()
        );
    }

    public function test_tudja_adresa_se_odbija(): void
    {
        $tudji = SubscriptionProperty::query()
            ->whereIn('subscription_id', Subscription::where('user_id', $this->pro->id)->pluck('id'))
            ->firstOrFail();

        $this->prijava(overrides: ['subscription_property_id' => $tudji->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subscription_property_id');
    }

    public function test_prekratak_opis_se_odbija(): void
    {
        $this->prijava(overrides: ['description' => 'curi'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    public function test_lista_naloga_vraca_najnovije_prvo(): void
    {
        $this->prijava()->assertCreated();
        $this->prijava(overrides: ['description' => 'Bojler ne grije vodu vise od dva dana.'])->assertCreated();

        $response = $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/jobs')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame('HAUS-2026-0002', $response->json('data.0.number'));
        $this->assertSame('HAUS-2026-0001', $response->json('data.1.number'));

        $response
            ->assertJsonPath('data.0.status', 'novo')
            ->assertJsonPath('data.0.type', 'redovno')
            ->assertJsonPath('data.0.category', 'Vodoinstalacije')
            ->assertJsonPath('data.0.technician', null)
            ->assertJsonPath('data.0.scheduled_window_start', null)
            ->assertJsonPath('data.0.warranty_until', null)
            ->assertJsonPath('data.0.deadline_missed_at', null);

        $this->assertNotNull($response->json('data.0.created_at'));
        $this->assertNotNull($response->json('data.0.deadline_at'));
        $this->assertSame('Bojler ne grije vodu vise od dva dana.', $response->json('data.0.title'));
    }

    public function test_dugacak_opis_se_krati_u_naslov(): void
    {
        $opis = trim(str_repeat('Voda curi iz cijevi. ', 10));

        $this->prijava(overrides: ['description' => $opis])->assertCreated();

        $response = $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/jobs')
            ->assertOk();

        $this->assertLessThanOrEqual(64, mb_strlen((string) $response->json('data.0.title')));
        $this->assertStringEndsWith('...', (string) $response->json('data.0.title'));
        $this->assertSame($opis, $response->json('data.0.description'));
    }

    public function test_detalj_naloga_nosi_nalaz_fotografije_i_racun(): void
    {
        $this->prijava(overrides: [
            'photo' => UploadedFile::fake()->image('kvar.jpg'),
        ])->assertCreated();

        $job = Job::query()->latest('id')->firstOrFail();

        $majstor = Technician::query()->firstOrFail();

        $job->update([
            'technician_id' => $majstor->id,
            'status' => JobStatus::UToku,
            'scheduled_window_start' => Carbon::create(2026, 8, 8, 10, 0, 0),
            'scheduled_window_end' => Carbon::create(2026, 8, 8, 12, 0, 0),
            'findings' => 'Zamijenjena baterija na lavabou.',
        ]);

        $response = $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/jobs/'.$job->id)
            ->assertOk();

        $response
            ->assertJsonPath('data.number', $job->number)
            ->assertJsonPath('data.findings', 'Zamijenjena baterija na lavabou.')
            ->assertJsonPath('data.technician.name', $majstor->name)
            ->assertJsonPath('data.invoice', null)
            ->assertJsonCount(1, 'data.photos')
            ->assertJsonPath('data.photos.0.type', 'prije');

        $this->assertStringContainsString('/storage/jobs/'.$job->id.'/', (string) $response->json('data.photos.0.url'));

        // Koraci prate stanje naloga.
        $steps = collect($response->json('data.steps'))->keyBy('key');
        $this->assertTrue($steps['prijava_primljena']['done']);
        $this->assertTrue($steps['majstor_dodijeljen']['done']);
        $this->assertTrue($steps['termin_potvrdjen']['done']);
        $this->assertTrue($steps['majstor_krenuo']['done']);
    }

    public function test_tudji_nalog_vraca_404(): void
    {
        $this->prijava($this->pro, [
            'subscription_property_id' => SubscriptionProperty::query()
                ->whereIn('subscription_id', Subscription::where('user_id', $this->pro->id)->pluck('id'))
                ->value('id'),
        ])->assertCreated();

        $tudji = Job::query()->latest('id')->firstOrFail();

        $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/jobs/'.$tudji->id)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Nalog nije pronađen.');
    }

    public function test_nepostojeci_nalog_vraca_404(): void
    {
        $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/jobs/999999')
            ->assertStatus(404);
    }

    public function test_mini_paket_ima_svoj_rok(): void
    {
        $mini = Package::where('slug', 'haus-mini')->firstOrFail();

        Subscription::where('user_id', $this->klijent->id)->update(['package_id' => $mini->id]);

        $this->prijava()->assertCreated();

        // HAUS Mini, redovno: 72 sata.
        $this->assertSame(
            Carbon::create(2026, 8, 10, 10, 0, 0)->toIso8601String(),
            Job::query()->latest('id')->firstOrFail()->deadline_at->toIso8601String()
        );
    }
}
