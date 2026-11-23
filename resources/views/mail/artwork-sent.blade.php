@extends('mail.layout')

@section('subject', 'The customer sent their artwork')

@section('body')
    <p style="margin:0 0 14px">Hi {{ $shopName }},</p>

    <p style="margin:0 0 14px">
        {{ $customerName }} sent a design file for
        <b>{{ $job->reference }}</b> ({{ $job->specSummary() }}).
        The job is yours to prepare — nothing moves until you send a proof.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
        <tr>
            <td style="padding:12px 14px;background:#FBF9F4;border-left:3px solid #C8A24A;border-radius:0 8px 8px 0;font-size:13.5px;color:#3A3F38;">
                {{ $fileName }} · {{ $fileSize }}
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
        <tr>
            <td style="border-radius:9px;background:#141712;">
                <a href="{{ route('console.jobs.show', $job) }}"
                   style="display:inline-block;padding:11px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13.5px;font-weight:600;color:#FFFFFF;text-decoration:none;">
                    Open the job
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:12.5px;color:#6B6F66">
        The file is attached to the job, not to this email — open the job to
        download it. If the file will not print well, say so in the proof note
        before the customer approves.
    </p>
@endsection
