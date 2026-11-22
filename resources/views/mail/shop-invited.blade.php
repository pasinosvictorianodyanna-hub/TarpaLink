@extends('mail.layout')

@section('subject', 'Your shop is approved — set your password')

@section('body')
    <p style="margin:0 0 14px">Hi {{ $ownerName }},</p>

    <p style="margin:0 0 14px">
        <b>{{ $shopName }}</b> has been approved on TarpaLink. Your shop console is ready —
        choose a password and it opens.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
        <tr>
            <td style="border-radius:9px;background:#141712;">
                <a href="{{ $link }}"
                   style="display:inline-block;padding:11px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13.5px;font-weight:600;color:#FFFFFF;text-decoration:none;">
                    Choose your password
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 14px;font-size:12.5px;color:#6B6F66">
        The link works once and expires in an hour. If it has expired, use
        “Forgot password” on the sign-in page with this address.
    </p>

    <p style="margin:0;font-size:12.5px;color:#6B6F66">
        Inside the console, the first thing to do is build your price matrix — the storefront
        goes live the moment it is complete.
    </p>
@endsection
