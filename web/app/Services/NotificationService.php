<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Mail\ObavjestenjeMail;
use App\Models\Job;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Jedno mjesto kroz koje idu sva obavjestenja klijentu.
 *
 * Tekst dolazi iz predloska u postavkama, uredjivanog iz admin panela.
 * Svako poslano obavjestenje ostavlja red u notifications_log, po kanalu.
 */
class NotificationService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Posalji obavjestenje po kljucu predloska.
     *
     * @param  array<string, string|int|float>  $vars
     * @return array<int, NotificationLog> upisani redovi, po kanalu
     */
    public function send(User $user, string $templateKey, array $vars = [], ?Job $job = null): array
    {
        $body = $this->settings->renderTemplate($templateKey, $vars);

        if ($body === null || $body === '') {
            Log::warning('Predlozak obavjestenja ne postoji.', ['template_key' => $templateKey]);

            return [];
        }

        $logs = [];

        if ($user->notif_email) {
            $logs[] = $this->log($user, $job, NotificationChannel::Mejl, $templateKey, $body);

            Mail::to($user->email)->queue(new ObavjestenjeMail($body, $templateKey));
        }

        if ($user->notif_push) {
            // TODO faza 7: poslati kroz FCM na sve uredjaje korisnika iz tabele devices.
            $logs[] = $this->log($user, $job, NotificationChannel::Push, $templateKey, $body);
        }

        return $logs;
    }

    /**
     * Tekst koji bi klijent dobio, bez slanja. Koristi ga admin pregled.
     *
     * @param  array<string, string|int|float>  $vars
     */
    public function preview(string $templateKey, array $vars = []): ?string
    {
        return $this->settings->renderTemplate($templateKey, $vars);
    }

    private function log(User $user, ?Job $job, NotificationChannel $channel, string $templateKey, string $body): NotificationLog
    {
        return NotificationLog::create([
            'user_id' => $user->id,
            'job_id' => $job?->id,
            'channel' => $channel,
            'template_key' => $templateKey,
            'body' => $body,
            'sent_at' => Carbon::now(),
        ]);
    }
}
