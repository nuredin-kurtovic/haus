<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * AI podrska nije mogla odgovoriti.
 *
 * Poruka je vec na bosanskom i smije ici korisniku. Detalji sa API-ja se
 * gube namjerno: klijentu ne treba status kod ni engleski tekst greske,
 * a u log ide previous.
 */
class SupportChatException extends RuntimeException
{
    public static function nijePodesen(): self
    {
        return new self('Podrška uživo trenutno nije dostupna. Pišite nam na mejl.');
    }

    public static function preopterecen(Throwable $previous): self
    {
        return new self(
            'Trenutno primamo previše pitanja. Pokušajte za minutu.',
            0,
            $previous
        );
    }

    public static function apiGreska(Throwable $previous): self
    {
        return new self(
            'Podrška trenutno ne radi. Pokušajte kasnije ili nam pišite preko kontakt forme.',
            0,
            $previous
        );
    }

    public static function prazanOdgovor(): self
    {
        return new self('Nisam dobio odgovor. Postavite pitanje ponovo.');
    }
}
