<?php

namespace Tests\Feature\Console;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\NotificationChannel;
use App\Mail\ObavjestenjeMail;
use App\Models\Job;
use App\Models\NotificationLog;
use App\Models\PriceCategory;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * haus:check-deadlines. Probijen rok znaci besplatnu intervenciju, upisanu
 * bez pitanja i tacno jednom po nalogu.
 */
class CheckDeadlinesTest extends TestCase
{
    use RefreshDatabase;

    private User $klijent;

    private Subscription $pretplata;

    private SubscriptionProperty $stan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        Carbon::setTestNow(Carbon::create(2026, 8, 10, 11, 0, 0));

        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $this->pretplata = $this->klijent->activeSubscription;
        $this->stan = $this->pretplata->properties->first();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_probijen_rok_upisuje_kredit_i_javlja_klijentu(): void
    {
        $job = $this->nalog(['deadline_at' => Carbon::now()->subHour()]);

        $this->artisan('haus:check-deadlines')->assertSuccessful();

        $job->refresh();

        $this->assertNotNull($job->deadline_missed_at);
        $this->assertSame(Carbon::now()->toIso8601String(), $job->deadline_missed_at->toIso8601String());
        $this->assertSame(1, (int) $this->pretplata->refresh()->free_interventions);

        $log = NotificationLog::where('template_key', 'rok_probijen')->get();

        $this->assertCount(2, $log, 'Ocekujemo red za mejl i red za push.');
        $this->assertEqualsCanonicalizing(['mejl', 'push'], $log->pluck('channel')->map->value->all());
        $this->assertSame($job->id, $log->first()->job_id);
        $this->assertStringContainsString('sljedeća intervencija je besplatna', $log->first()->body);

        Mail::assertQueued(
            ObavjestenjeMail::class,
            fn (ObavjestenjeMail $mail): bool => $mail->templateKey === 'rok_probijen'
        );
    }

    public function test_drugi_prolaz_ne_duplira_kredit(): void
    {
        $this->nalog(['deadline_at' => Carbon::now()->subHour()]);

        $this->artisan('haus:check-deadlines')->assertSuccessful();
        $this->artisan('haus:check-deadlines')->assertSuccessful();

        $this->assertSame(1, (int) $this->pretplata->refresh()->free_interventions);
        $this->assertSame(
            2,
            NotificationLog::where('template_key', 'rok_probijen')->count(),
            'Obavjestenje ide jednom, po jedan red za svaki kanal.'
        );
    }

    public function test_nalog_u_roku_i_zavrsen_nalog_se_ne_diraju(): void
    {
        $uRoku = $this->nalog(['deadline_at' => Carbon::now()->addHour()]);
        $zavrsen = $this->nalog([
            'deadline_at' => Carbon::now()->subDay(),
            'status' => JobStatus::Zavrseno,
        ]);

        $this->artisan('haus:check-deadlines')->assertSuccessful();

        $this->assertNull($uRoku->refresh()->deadline_missed_at);
        $this->assertNull($zavrsen->refresh()->deadline_missed_at);
        $this->assertSame(0, (int) $this->pretplata->refresh()->free_interventions);
        $this->assertSame(0, NotificationLog::where('template_key', 'rok_probijen')->count());
    }

    public function test_svaki_probijen_nalog_nosi_svoj_kredit(): void
    {
        $this->nalog(['deadline_at' => Carbon::now()->subHour()]);
        $this->nalog(['deadline_at' => Carbon::now()->subHours(3)]);

        $this->artisan('haus:check-deadlines')->assertSuccessful();

        $this->assertSame(2, (int) $this->pretplata->refresh()->free_interventions);
    }

    public function test_nalog_bez_pretplate_se_oznaci_ali_ne_kreditira(): void
    {
        $job = $this->nalog([
            'deadline_at' => Carbon::now()->subHour(),
            'subscription_id' => null,
            'subscription_property_id' => null,
        ]);

        $this->artisan('haus:check-deadlines')->assertSuccessful();

        $this->assertNotNull($job->refresh()->deadline_missed_at);
        $this->assertSame(0, (int) $this->pretplata->refresh()->free_interventions);
        $this->assertSame(
            0,
            NotificationLog::where('template_key', 'rok_probijen')->count(),
            'Bez pretplate nema kredita, pa ni obecanja o besplatnoj intervenciji.'
        );
    }

    public function test_klijent_bez_mejla_dobija_samo_push_red(): void
    {
        $this->klijent->update(['notif_email' => false]);

        $this->nalog(['deadline_at' => Carbon::now()->subHour()]);

        $this->artisan('haus:check-deadlines')->assertSuccessful();

        $log = NotificationLog::where('template_key', 'rok_probijen')->get();

        $this->assertCount(1, $log);
        $this->assertSame(NotificationChannel::Push, $log->first()->channel);

        Mail::assertNotQueued(ObavjestenjeMail::class);
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
            'type' => JobType::Redovno,
            'status' => JobStatus::Novo,
            'description' => 'Slavina u kupatilu curi.',
            'deadline_at' => Carbon::now()->addHours(48),
        ], $overrides));
    }
}
