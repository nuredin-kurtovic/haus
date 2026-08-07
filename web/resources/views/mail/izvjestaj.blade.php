@extends('mail.layout')

@section('sadrzaj')
    <p style="margin:0 0 16px 0; font-size:20px; font-weight:600;">Izvještaj o intervenciji</p>

    <p style="margin:0 0 16px 0;">
        Posao je završen. Nalog {{ $job->number }}@if ($job->technician), majstor {{ $job->technician->name }}@endif.
    </p>

    <p style="margin:0 0 8px 0; font-weight:600;">Nalaz</p>
    <p style="margin:0 0 20px 0;">{{ $job->findings }}</p>

    @if ($stavke->isNotEmpty())
        <p style="margin:0 0 8px 0; font-weight:600;">Urađeno</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; border-collapse:collapse;">
            @foreach ($stavke as $stavka)
                <tr>
                    <td style="padding:10px 12px; font-size:14px; {{ $loop->even ? 'background-color:#FFFCF2;' : '' }}">{{ $stavka->name }} x{{ (int) $stavka->qty }}</td>
                    <td style="padding:10px 12px; font-size:14px; font-weight:600; font-variant-numeric:tabular-nums; {{ $loop->even ? 'background-color:#FFFCF2;' : '' }}" align="right">{{ number_format((float) $stavka->line_total, 2, ',', '.') }} KM</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($materijali->isNotEmpty())
        <p style="margin:0 0 8px 0; font-weight:600;">Materijal</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; border-collapse:collapse;">
            @foreach ($materijali as $materijal)
                <tr>
                    <td style="padding:10px 12px; font-size:14px; {{ $loop->even ? 'background-color:#FFFCF2;' : '' }}">{{ $materijal->name }} x{{ rtrim(rtrim(number_format((float) $materijal->qty, 2, ',', '.'), '0'), ',') }}</td>
                    <td style="padding:10px 12px; font-size:14px; font-weight:600; font-variant-numeric:tabular-nums; {{ $loop->even ? 'background-color:#FFFCF2;' : '' }}" align="right">{{ number_format((float) $materijal->line_total, 2, ',', '.') }} KM</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($racun)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0; border-collapse:collapse;">
            <tr>
                <td style="padding:10px 12px; background-color:#FFFCF2; font-size:13px;">Rad</td>
                <td style="padding:10px 12px; background-color:#FFFCF2; font-size:15px; font-weight:600; font-variant-numeric:tabular-nums;" align="right">{{ number_format((float) $racun->labor_total, 2, ',', '.') }} KM</td>
            </tr>
            <tr>
                <td style="padding:10px 12px; font-size:13px;">Materijal</td>
                <td style="padding:10px 12px; font-size:15px; font-weight:600; font-variant-numeric:tabular-nums;" align="right">{{ number_format((float) $racun->material_total, 2, ',', '.') }} KM</td>
            </tr>
            <tr>
                <td style="padding:10px 12px; background-color:#FFFCF2; font-size:13px;">Za naplatu</td>
                <td style="padding:10px 12px; background-color:#FFFCF2; font-size:16px; font-weight:600; font-variant-numeric:tabular-nums;" align="right">{{ number_format((float) $racun->total, 2, ',', '.') }} KM</td>
            </tr>
        </table>

        @if ((float) $racun->total === 0.0)
            <p style="margin:0 0 20px 0;">Ovaj izlazak je pokriven vašom pretplatom. Nema šta da platite.</p>
        @endif
    @endif

    @if (count($prije) || count($poslije))
        <p style="margin:0 0 8px 0; font-weight:600;">Fotografije</p>
        <ul style="margin:0 0 20px 0; padding-left:18px;">
            @foreach ($prije as $url)
                <li style="margin:0 0 6px 0;"><a href="{{ $url }}" style="color:#FE5100;">Prije {{ $loop->iteration }}</a></li>
            @endforeach
            @foreach ($poslije as $url)
                <li style="margin:0 0 6px 0;"><a href="{{ $url }}" style="color:#FE5100;">Poslije {{ $loop->iteration }}</a></li>
            @endforeach
        </ul>
    @endif

    @if ($garancijaDo)
        <p style="margin:0;">Garancija na rad vrijedi do {{ $garancijaDo }} Nalaz i fotografije ostaju u vašem kartonu doma.</p>
    @else
        <p style="margin:0;">Nalaz i fotografije ostaju u vašem kartonu doma.</p>
    @endif
@endsection
