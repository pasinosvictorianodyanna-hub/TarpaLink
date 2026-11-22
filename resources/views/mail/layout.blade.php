{{--
    The shell every TarpaLink email sits in.

    Table-based and inline-styled on purpose: mail clients are not browsers, and
    the panel's compiled theme means nothing here. The paper/ink palette is kept
    so a customer recognises the sender, but nothing else is shared — an email
    that depends on an external stylesheet arrives unstyled in half the clients
    it is read in.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('subject', 'TarpaLink')</title>
</head>
<body style="margin:0;padding:0;background:#F6F4EF;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F4EF;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#FFFFFF;border:1px solid #E4DFD4;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="padding:20px 26px;border-bottom:1px solid #E4DFD4;">
                            <span style="font-family:Georgia,'Times New Roman',serif;font-size:17px;font-weight:700;color:#141712;letter-spacing:-.2px;">TarpaLink</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.65;color:#141712;">
                            @yield('body')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 26px;border-top:1px solid #E4DFD4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11.5px;line-height:1.6;color:#6B6F66;">
                            You are receiving this because you have a TarpaLink account.
                            Print jobs, proofs and payouts — not marketing.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
