<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\RenewalService;
use Illuminate\Console\Command;

/**
 * Godisnja obnova pretplate. Vrti se jednom dnevno, prije radnog vremena.
 *
 * Cijeli tok je u App\Services\RenewalService, ova komanda je samo petlja.
 */
class ProcessRenewals extends Command
{
    protected $signature = 'haus:process-renewals';

    protected $description = 'Obnovi pretplate kojima je istekao rok: MIT naplata po tokenu ili uplatnica.';

    public function handle(RenewalService $renewals): int
    {
        $dospjele = $renewals->dospjele();

        $ishodi = [
            RenewalService::OBNOVLJENA => 0,
            RenewalService::ODBIJENA => 0,
            RenewalService::BEZ_TOKENA => 0,
            RenewalService::ISTEKLA => 0,
            RenewalService::PRESKOCENA => 0,
        ];

        foreach ($dospjele as $subscription) {
            /** @var Subscription $subscription */
            $ishod = $renewals->obradi($subscription);

            $ishodi[$ishod] = ($ishodi[$ishod] ?? 0) + 1;
        }

        $this->info(
            'Dospjelih pretplata: '.$dospjele->count().
            '. Obnovljeno: '.$ishodi[RenewalService::OBNOVLJENA].
            '. Odbijeno: '.$ishodi[RenewalService::ODBIJENA].
            '. Bez tokena: '.$ishodi[RenewalService::BEZ_TOKENA].
            '. Bez automatske obnove: '.$ishodi[RenewalService::ISTEKLA].'.'
        );

        return self::SUCCESS;
    }
}
