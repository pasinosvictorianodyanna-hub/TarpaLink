@extends('mail.layout')

@section('subject', 'About your job')

@section('body')
    <p style="margin:0 0 14px">Hi {{ $customerName }},</p>

    <p style="margin:0 0 14px">
        {{ $shopName }} is waiting on you for <b>{{ $job->reference }}</b>
        ({{ $job->specSummary() }}).
    </p>

    <p style="margin:0 0 18px">
        @switch($job->status)
            @case('quoted')
                The order is in, but the payment has not come through yet. Opening
                the job and paying is the one thing left before the press starts.
                @break

            @case('awaiting_proof')
                A proof is ready and waiting for your approval. Nothing goes on the
                press until you have looked at it.
                @break

            @case('ready')
                It is finished and on the shelf. Drop by whenever suits you.
                @break

            @default
                There is something on this job that needs you rather than the shop.
        @endswitch
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
        <tr>
            <td style="border-radius:9px;background:#141712;">
                <a href="{{ route('jobs.show', $job) }}"
                   style="display:inline-block;padding:11px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13.5px;font-weight:600;color:#FFFFFF;text-decoration:none;">
                    Open the job
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:12.5px;color:#6B6F66">
        Replying to this email files your answer on the job, so the shop sees it
        where the job is.
    </p>
@endsection
