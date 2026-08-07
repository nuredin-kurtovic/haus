@extends('mail.layout')

@section('sadrzaj')
    <p style="margin:0 0 16px 0; font-size:20px; font-weight:600;">Uplatnica za pretplatu</p>

    <p style="margin:0 0 16px 0;">
        Poštovani, hvala Vam na prijavi. Pretplata se aktivira čim uplata legne na račun.
        Uplata se najčešće vidi isti ili sljedeći radni dan.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; border-collapse:collapse;">
        <tr>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:13px;">Iznos</td>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:16px; font-weight:600; font-variant-numeric:tabular-nums;" align="right">{{ number_format($iznos, 2, ',', '.') }} KM</td>
        </tr>
        <tr>
            <td style="padding:10px 12px; font-size:13px;">Poziv na broj</td>
            <td style="padding:10px 12px; font-size:16px; font-weight:600; font-variant-numeric:tabular-nums;" align="right">{{ $pozivNaBroj }}</td>
        </tr>
        <tr>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:13px;">Račun primaoca</td>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:16px; font-weight:600; font-variant-numeric:tabular-nums;" align="right">{{ $racun }}</td>
        </tr>
        <tr>
            <td style="padding:10px 12px; font-size:13px;">Primalac</td>
            <td style="padding:10px 12px; font-size:16px; font-weight:600;" align="right">{{ $primalac }}</td>
        </tr>
    </table>

    <p style="margin:0 0 8px 0; font-weight:600;">Kako platiti</p>
    <p style="margin:0 0 16px 0;">
        Uplatu možete izvršiti u banci, na pošti ili kroz mobilno bankarstvo.
        Obavezno upišite poziv na broj {{ $pozivNaBroj }}, po njemu prepoznajemo Vašu uplatu.
    </p>

    <p style="margin:0;">
        Kada uplata legne, dobijate račun na mejl i pretplata postaje aktivna.
        Prvu prijavu možete poslati odmah nakon toga.
    </p>
@endsection
