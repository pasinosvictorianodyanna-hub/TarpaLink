@extends('mail.layout')

@section('subject', 'Your proof is ready to approve')

@section('body')
    <p style="margin:0 0 14px">Hi {{ $customerName }},</p>

    <p style="margin:0 0 14px">
        {{ $shopName }} has sent you a proof for
        <b>{{ $job->reference }}</b> ({{ $job->specSummary() }}).
        Nothing goes on the press until you approve it.
    </p>

    @if ($note)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
            <tr>
                <td style="padding:12px 14px;background:#FBF9F4;border-left:3px solid #C8A24A;border-radius:0 8px 8px 0;font-size:13.5px;color:#3A3F38;">
                    {{ $note }}
                </td>
            </tr>
        </table>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
        <tr>
            <td style="border-radius:9px;background:#141712;">
                <a href="{{ route('jobs.show', $job) }}"
                   style="display:inline-block;padding:11px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13.5px;font-weight:600;color:#FFFFFF;text-decoration:none;">
                    Review the proof
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:12.5px;color:#6B6F66">
        This is version {{ $version }}. If something needs changing, ask for changes
        on that page and the shop will send a new version.
    </p>
@endsection
