<?php

namespace App\Contracts;

/**
 * Rezultat iniciranja placanja: gdje korisnika poslati i pod kojom referencom
 * ga gateway zna. Referenca je veza izmedju nase uplate i webhooka.
 */
final class PaymentInitiation
{
    public function __construct(
        public readonly string $redirectUrl,
        public readonly string $gatewayReference,
    ) {}

    /**
     * @return array{redirect_url: string, gateway_reference: string}
     */
    public function toArray(): array
    {
        return [
            'redirect_url' => $this->redirectUrl,
            'gateway_reference' => $this->gatewayReference,
        ];
    }
}
