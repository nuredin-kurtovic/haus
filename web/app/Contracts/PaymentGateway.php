<?php

namespace App\Contracts;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentToken;
use Illuminate\Http\Request;

/**
 * Sav dodir sa platnim prometom ide kroz ovaj interfejs.
 *
 * Lokalno radi FakeGateway koji simulira 3DS redirect i webhook.
 * U produkciji radi MonriGateway, driver se bira u config/services.php.
 */
interface PaymentGateway
{
    /**
     * Otvori placanje za fakturu i vrati gdje korisnika poslati.
     */
    public function initiate(Invoice $invoice): PaymentInitiation;

    /**
     * Da li webhook zaista dolazi od gatewaya.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Pun ili djelimican povrat. Iznos null znaci pun povrat.
     */
    public function refund(Payment $payment, ?float $amount = null): bool;

    /**
     * Naplata po spremljenom tokenu, bez prisustva klijenta (MIT).
     *
     * Koristi je godisnja obnova pretplate. Odgovor stize odmah, nema
     * webhooka ni 3DS koraka. Odbijena transakcija je uredan ishod
     * (approved = false), a kvar u komunikaciji je PaymentGatewayException.
     */
    public function chargeToken(PaymentToken $token, Invoice $invoice): PaymentResult;
}
