<?php

namespace Tests\Feature\Services;

use App\Enums\NotificationChannel;
use App\Mail\ObavjestenjeMail;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notifications;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        $this->notifications = app(NotificationService::class);
    }

    private function klijent(): User
    {
        return User::where('email', 'klijent@haus.ba')->firstOrFail();
    }

    public function test_salje_na_oba_kanala_i_renderuje_predlozak(): void
    {
        $user = $this->klijent();

        $logs = $this->notifications->send($user, 'pretplata_aktivna', [
            'paket' => 'HAUS Plus',
            'vrijedi_do' => '07.08.2027.',
        ]);

        $this->assertCount(2, $logs);
        $this->assertSame(2, NotificationLog::count());

        $body = NotificationLog::first()->body;

        $this->assertStringContainsString('HAUS Plus', $body);
        $this->assertStringContainsString('07.08.2027.', $body);
        $this->assertStringNotContainsString('{paket}', $body);
        $this->assertStringNotContainsString('{vrijedi_do}', $body);

        $this->assertEqualsCanonicalizing(
            [NotificationChannel::Mejl->value, NotificationChannel::Push->value],
            NotificationLog::pluck('channel')->map->value->all()
        );

        Mail::assertQueued(ObavjestenjeMail::class, function (ObavjestenjeMail $mail) use ($user, $body) {
            return $mail->hasTo($user->email) && $mail->body === $body;
        });
    }

    public function test_postuje_iskljucen_mejl(): void
    {
        $user = $this->klijent();
        $user->update(['notif_email' => false]);

        $this->notifications->send($user, 'pretplata_aktivna', ['paket' => 'HAUS Plus', 'vrijedi_do' => '1']);

        $this->assertSame(1, NotificationLog::count());
        $this->assertSame(NotificationChannel::Push, NotificationLog::first()->channel);

        Mail::assertNothingQueued();
    }

    public function test_postuje_iskljucen_push(): void
    {
        $user = $this->klijent();
        $user->update(['notif_push' => false]);

        $this->notifications->send($user, 'pretplata_aktivna', ['paket' => 'HAUS Plus', 'vrijedi_do' => '1']);

        $this->assertSame(1, NotificationLog::count());
        $this->assertSame(NotificationChannel::Mejl, NotificationLog::first()->channel);

        Mail::assertQueued(ObavjestenjeMail::class);
    }

    public function test_bez_ijednog_kanala_ne_upisuje_nista(): void
    {
        $user = $this->klijent();
        $user->update(['notif_push' => false, 'notif_email' => false]);

        $this->assertSame([], $this->notifications->send($user, 'pretplata_aktivna', []));
        $this->assertSame(0, NotificationLog::count());
    }

    public function test_nepoznat_predlozak_ne_puca_i_ne_salje(): void
    {
        $this->assertSame([], $this->notifications->send($this->klijent(), 'ne-postoji', []));

        $this->assertSame(0, NotificationLog::count());

        Mail::assertNothingQueued();
    }

    public function test_preview_vraca_tekst_bez_slanja(): void
    {
        $text = $this->notifications->preview('pretplata_aktivna', [
            'paket' => 'HAUS Mini',
            'vrijedi_do' => '01.01.2027.',
        ]);

        $this->assertStringContainsString('HAUS Mini', (string) $text);
        $this->assertSame(0, NotificationLog::count());

        Mail::assertNothingQueued();
    }
}
