@extends('mail.layout')

@section('sadrzaj')
    <p style="margin:0 0 16px 0; font-size:20px; font-weight:600;">Zahtjev za ponudu</p>

    <p style="margin:0 0 16px 0;">
        Novi klijent traži HAUS Pro za {{ $stanovi->count() }} stanova.
        Deset i više stanova nema automatsku naplatu, ponudu radi dispečer.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; border-collapse:collapse;">
        <tr>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:13px;">Klijent</td>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:15px; font-weight:600;" align="right">{{ $klijent->name }}</td>
        </tr>
        <tr>
            <td style="padding:10px 12px; font-size:13px;">Mejl</td>
            <td style="padding:10px 12px; font-size:15px; font-weight:600;" align="right">{{ $klijent->email }}</td>
        </tr>
        <tr>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:13px;">Paket</td>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:15px; font-weight:600;" align="right">{{ $paket->name }}</td>
        </tr>
        <tr>
            <td style="padding:10px 12px; font-size:13px;">Broj stanova</td>
            <td style="padding:10px 12px; font-size:15px; font-weight:600; font-variant-numeric:tabular-nums;" align="right">{{ $stanovi->count() }}</td>
        </tr>
    </table>

    <p style="margin:0 0 8px 0; font-weight:600;">Adrese</p>
    <ul style="margin:0 0 16px 0; padding-left:18px;">
        @foreach ($stanovi as $stan)
            <li style="margin:0 0 6px 0;">{{ $stan->street }}, {{ $stan->city?->name }}</li>
        @endforeach
    </ul>

    <p style="margin:0;">
        Pretplata je upisana u stanju ponuda. Faktura nije izdata i naplata nije pokrenuta.
    </p>
@endsection
