<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\NotificationService;
use App\Services\RenewalService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Podsjetnik na obnovu, 60 dana prije isteka. Vrti se jednom dnevno.
 *
 * Idempotentno preko renewal_reminder_sent_at, pa vise pokretanja u istom danu
 * salje tacno jedan podsjetnik. PaymentProcessor to polje ocisti pri produzenju,
 * pa sljedeca godina dobija svoj podsjetnik.
 *
 * Odluka: podsjetnik ide samo pretplatama sa upaljenom automatskom obnovom.
 * Predlozak kaze da je obnova automatska i navodi iznos, pa bi klijentu koji
 * je obnovu ugasio poslao netacnu informaciju. Njemu je otkazivanje vec
 * potvrdjeno kad ga je zatrazio.
 */
class SendRenewalReminders extends Command
{
    protected $signature = 'haus:renewal-reminders {--dana=60 : Koliko dana prije isteka ide podsjetnik}';

    protected $description = 'Posalje podsjetnik na obnovu pretplatama koje isticu za 60 dana.';

    public function handle(NotificationService $notifications, RenewalService $renewals): int
    {
        $dana = max(1, (int) $this->option('dana'));
        $datum = Carbon::now()->addDays($dana)->toDateString();

        $pretplate = Subscription::query()
            ->where('status', SubscriptionStatus::Aktivna)
            ->where('auto_renew', true)
            ->whereNull('renewal_reminder_sent_at')
            ->whereDate('ends_at', $datum)
            ->with(['user', 'package', 'properties'])
            ->orderBy('id')
            ->get();

        $poslano = 0;

        foreach ($pretplate as $subscription) {
            if (! $subscription->user) {
                continue;
            }

            $notifications->send($subscription->user, 'obnova_podsjetnik', [
                'datum' => optional($subscription->ends_at)->format('d.m.Y') ?? '',
                'iznos' => $renewals->iznos($subscription),
            ]);

            $subscription->forceFill(['renewal_reminder_sent_at' => Carbon::now()])->save();

            $poslano++;
        }

        $this->info('Podsjetnika na obnovu poslano: '.$poslano.'. Datum isteka: '.$datum.'.');

        return self::SUCCESS;
    }
}
