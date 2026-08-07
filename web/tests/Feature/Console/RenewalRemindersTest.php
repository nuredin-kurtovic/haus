<?php

namespace Tests\Feature\Console;

use App\Enums\SubscriptionStatus;
use App\Mail\ObavjestenjeMail;
use App\Models\NotificationLog;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * haus:renewal-reminders. Tacno 60 dana prije isteka, tacno jednom.
 */
class RenewalRemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $klijent;

    private Subscription $pretplata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        Carbon::setTestNow(Carbon::create(2026, 8, 10, 9, 0, 0));

        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $this->pretplata = $this->klijent->activeSubscription;

        // Pretplata sa druge strane sesezdesetog dana ne smije smetati.
        Subscription::where('id', '!=', $this->pretplata->id)
            ->update(['ends_at' => Carbon::now()->addDays(120)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_podsjetnik_ide_tacno_na_sezdeset_dana(): void
    {
        $this->pretplata->update([
            'ends_at' => Carbon::now()->addDays(60)->setTime(0, 0),
            'renewal_reminder_sent_at' => null,
        ]);

        $this->artisan('haus:renewal-reminders')->assertSuccessful();

        $log = NotificationLog::where('template_key', 'obnova_podsjetnik')->get();

        $this->assertCount(2, $log, 'Ocekujemo red za mejl i red za push.');
        $this->assertStringContainsString('09.10.2026', $log->first()->body);
        // HAUS Plus, 169 KM, jedna adresa.
        $this->assertStringContainsString('169 KM', $log->first()->body);
        $this->assertStringNotContainsString('{', $log->first()->body);

        $this->assertNotNull($this->pretplata->refresh()->renewal_reminder_sent_at);

        Mail::assertQueued(
            ObavjestenjeMail::class,
            fn (ObavjestenjeMail $mail): bool => $mail->templateKey === 'obnova_podsjetnik'
        );
    }

    public function test_dan_ranije_i_dan_kasnije_ne_dobijaju_podsjetnik(): void
    {
        $this->pretplata->update(['ends_at' => Carbon::now()->addDays(59)]);
        $this->artisan('haus:renewal-reminders')->assertSuccessful();

        $this->pretplata->update(['ends_at' => Carbon::now()->addDays(61)]);
        $this->artisan('haus:renewal-reminders')->assertSuccessful();

        $this->assertSame(0, NotificationLog::where('template_key', 'obnova_podsjetnik')->count());
        $this->assertNull($this->pretplata->refresh()->renewal_reminder_sent_at);
    }

    public function test_drugi_prolaz_ne_salje_dva_puta(): void
    {
        $this->pretplata->update([
            'ends_at' => Carbon::now()->addDays(60),
            'renewal_reminder_sent_at' => null,
        ]);

        $this->artisan('haus:renewal-reminders')->assertSuccessful();
        $this->artisan('haus:renewal-reminders')->assertSuccessful();

        $this->assertSame(2, NotificationLog::where('template_key', 'obnova_podsjetnik')->count());
        Mail::assertQueuedCount(1);
    }

    public function test_bez_automatske_obnove_nema_podsjetnika(): void
    {
        $this->pretplata->update([
            'ends_at' => Carbon::now()->addDays(60),
            'auto_renew' => false,
        ]);

        $this->artisan('haus:renewal-reminders')->assertSuccessful();

        $this->assertSame(0, NotificationLog::where('template_key', 'obnova_podsjetnik')->count());
    }

    public function test_neaktivna_pretplata_nema_podsjetnika(): void
    {
        $this->pretplata->update([
            'ends_at' => Carbon::now()->addDays(60),
            'status' => SubscriptionStatus::Otkazana,
        ]);

        $this->artisan('haus:renewal-reminders')->assertSuccessful();

        $this->assertSame(0, NotificationLog::where('template_key', 'obnova_podsjetnik')->count());
    }

    public function test_iznos_prati_trenutni_broj_adresa(): void
    {
        $pro = User::where('email', 'pro@haus.ba')->firstOrFail();
        $proPretplata = $pro->activeSubscription;

        $proPretplata->update([
            'ends_at' => Carbon::now()->addDays(60),
            'renewal_reminder_sent_at' => null,
        ]);

        $this->artisan('haus:renewal-reminders')->assertSuccessful();

        $log = NotificationLog::where('user_id', $pro->id)
            ->where('template_key', 'obnova_podsjetnik')
            ->firstOrFail();

        // HAUS Pro, 390 KM po stanu, tri stana, popust na kolicinu 10 posto.
        $this->assertStringContainsString('1053 KM', $log->body);
    }
}
