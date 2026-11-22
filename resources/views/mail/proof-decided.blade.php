@extends('mail.layout')

@section('subject', 'Proof '.($approved ? 'approved' : 'sent back').' — '.$job->reference)

@section('body')
    <p style="margin:0 0 14px">Hi {{ $ownerName }},</p>

    @if ($approved)
        <p style="margin:0 0 14px">
            {{ $customerName }} approved the proof for <b>{{ $job->reference }}</b>
            ({{ $job->specSummary() }}). It is ready to go on the press.
        </p>
    @else
        <p style="margin:0 0 14px">
            {{ $customerName }} asked for changes to the proof for
            <b>{{ $job->reference }}</b>. The job is still waiting, so it needs a
            new version from you.
        </p>

        @if ($note)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
                <tr>
                    <td style="padding:12px 14px;background:#FDF3F1;border-left:3px solid #B42318;border-radius:0 8px 8px 0;font-size:13.5px;color:#3A3F38;">
                        {{ $note }}
                    </td>
                </tr>
            </table>
        @endif
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 6px;">
        <tr>
            <td style="border-radius:9px;background:#141712;">
                <a href="{{ $jobUrl }}"
                   style="display:inline-block;padding:11px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13.5px;font-weight:600;color:#FFFFFF;text-decoration:none;">
                    Open the job
                </a>
            </td>
        </tr>
    </table>
@endsection
