@extends('mail.layout')

@section('sadrzaj')
    <p style="margin:0 0 16px 0; font-size:20px; font-weight:600;">Račun broj {{ $invoice->number }}</p>

    <p style="margin:0 0 16px 0;">
        Uplata je zaprimljena. Vaša pretplata je aktivna i prvu prijavu možete poslati odmah.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; border-collapse:collapse;">
        <tr>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:13px;">Iznos</td>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:16px; font-weight:600; font-variant-numeric:tabular-nums;" align="right">{{ number_format($iznos, 2, ',', '.') }} KM</td>
        </tr>
        <tr>
            <td style="padding:10px 12px; font-size:13px;">Datum uplate</td>
            <td style="padding:10px 12px; font-size:16px; font-weight:600; font-variant-numeric:tabular-nums;" align="right">{{ optional($invoice->paid_at)->format('d.m.Y.') }}</td>
        </tr>
        <tr>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:13px;">Izdavalac</td>
            <td style="padding:10px 12px; background-color:#FFFCF2; font-size:16px; font-weight:600;" align="right">{{ $primalac }}</td>
        </tr>
    </table>

    <p style="margin:0;">
        Račun je dostupan i u Vašem profilu, u dijelu Pretplata.
    </p>
@endsection
