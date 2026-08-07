<?php

namespace App\Contracts;

/**
 * Rezultat iniciranja placanja: gdje korisnika poslati i pod kojom referencom
 * ga gateway zna. Referenca je veza izmedju nase uplate i webhooka.
 *
 * Monri WebPay se otvara POST zahtjevom sa skrivenim poljima, pa uz adresu
 * ide i metoda i polja forme. FakeGateway je obican GET redirect i polja
 * ostavlja prazna, pa stariji klijent koji gleda samo redirect_url i dalje radi.
 */
final class PaymentInitiation
{
    /**
     * @param  array<string, string>  $fields
     */
    public function __construct(
        public readonly string $redirectUrl,
        public readonly string $gatewayReference,
        public readonly string $method = 'GET',
        public readonly array $fields = [],
    ) {}

    /**
     * @return array{redirect_url: string, gateway_reference: string, method: string, fields: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'redirect_url' => $this->redirectUrl,
            'gateway_reference' => $this->gatewayReference,
            'method' => $this->method,
            'fields' => $this->fields,
        ];
    }
}
