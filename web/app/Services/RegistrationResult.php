<?php

namespace App\Services;

use App\Contracts\PaymentInitiation;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;

final class RegistrationResult
{
    public function __construct(
        public readonly User $user,
        public readonly Subscription $subscription,
        public readonly int $total,
        public readonly ?Invoice $invoice = null,
        public readonly ?PaymentInitiation $initiation = null,
    ) {}

    /**
     * Registracija je zavrsila kao zahtjev za ponudu, bez fakture i naplate.
     */
    public function isPonuda(): bool
    {
        return $this->invoice === null;
    }
}
