{{-- Zajednicki okvir za sve HAUS mejlove. Bez zaobljenja, bez sjenki, Poppins. --}}
<div style="margin:0; padding:0; background-color:#FFFCF2; font-family:Poppins, 'Helvetica Neue', Arial, sans-serif; color:#252422;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFFCF2;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#FFFFFF;">
                    <tr>
                        <td style="background-color:#FE5100; padding:24px;">
                            <span style="color:#FFFCF2; font-size:24px; font-weight:600; letter-spacing:2px;">HAUS</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 24px; font-size:15px; line-height:1.6; color:#252422;">
                            @yield('sadrzaj')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 24px; background-color:#FFFCF2; font-size:12px; line-height:1.6; color:#252422;">
                            Poznata cijena. Dogovoren rok. Pisana garancija.<br>
                            {{ config('services.haus.company_name') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
