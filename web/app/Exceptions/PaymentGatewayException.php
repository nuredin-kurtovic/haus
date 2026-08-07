<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Gateway nije odgovorio ili je odgovorio greskom na nivou protokola.
 *
 * Ovo nije odbijena transakcija. Odbijena transakcija je uredan odgovor i
 * vraca se kao PaymentResult sa approved = false. Ovdje je kvar u komunikaciji:
 * timeout, 5xx, neocekivan format. Domenski kod hvata ovu gresku i odlucuje
 * sam sta je poruka korisniku.
 */
class PaymentGatewayException extends RuntimeException
{
    public static function transport(string $operacija, Throwable $previous): self
    {
        return new self(
            'Gateway nije odgovorio na operaciju ['.$operacija.']: '.$previous->getMessage(),
            0,
            $previous
        );
    }

    public static function odgovor(string $operacija, int $status, string $body): self
    {
        return new self(
            'Gateway je na operaciju ['.$operacija.'] vratio status '.$status.': '.mb_substr($body, 0, 500)
        );
    }
}
