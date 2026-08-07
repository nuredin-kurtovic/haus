<?php

namespace App\Contracts;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Sav dodir sa platnim prometom ide kroz ovaj interfejs.
 *
 * Lokalno radi FakeGateway koji simulira 3DS redirect i webhook.
 * Pravi MonriGateway dolazi u fazi 6, driver se bira u config/services.php.
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
}
