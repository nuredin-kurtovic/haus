<?php

namespace App\Contracts;

/**
 * Ishod naplate koju je pokrenuo trgovac, bez prisustva klijenta (MIT).
 *
 * Koristi je godisnja obnova pretplate: gateway odmah kaze da li je proslo,
 * pa nema webhooka koji bi cekali.
 */
final class PaymentResult
{
    /**
     * @param  array<string, mixed>  $payload  sirovi odgovor gatewaya, ide u payments.gateway_payload
     */
    public function __construct(
        public readonly bool $approved,
        public readonly string $reference,
        public readonly array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function approved(string $reference, array $payload = []): self
    {
        return new self(true, $reference, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function declined(string $reference, array $payload = []): self
    {
        return new self(false, $reference, $payload);
    }
}
