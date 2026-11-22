@extends('mail.layout')

@section('subject', 'Receipt for '.$job->reference)

@section('body')
    <p style="margin:0 0 14px">Hi {{ $customerName }},</p>

    <p style="margin:0 0 16px">
        We received your payment for <b>{{ $job->reference }}</b> at
        {{ $shopName }}. Here is what it was for.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;font-size:13.5px;">
        <tr>
            <td style="padding:9px 0;border-bottom:1px solid #EFEBE2;color:#6B6F66;">Service</td>
            <td style="padding:9px 0;border-bottom:1px solid #EFEBE2;text-align:right;">{{ $job->service?->name ?? 'Print job' }}</td>
        </tr>
        <tr>
            <td style="padding:9px 0;border-bottom:1px solid #EFEBE2;color:#6B6F66;">Size and quantity</td>
            <td style="padding:9px 0;border-bottom:1px solid #EFEBE2;text-align:right;">{{ $job->specSummary() }}</td>
        </tr>
        <tr>
            <td style="padding:9px 0;border-bottom:1px solid #EFEBE2;color:#6B6F66;">Subtotal</td>
            <td style="padding:9px 0;border-bottom:1px solid #EFEBE2;text-align:right;">{{ \App\Support\Money::format($job->subtotal) }}</td>
        </tr>
        @if ($job->discount > 0)
            <tr>
                <td style="padding:9px 0;border-bottom:1px solid #EFEBE2;color:#6B6F66;">Quantity discount</td>
                <td style="padding:9px 0;border-bottom:1px solid #EFEBE2;text-align:right;">−{{ \App\Support\Money::format($job->discount) }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding:10px 0;border-bottom:1px solid #EFEBE2;color:#6B6F66;">Total</td>
            <td style="padding:10px 0;border-bottom:1px solid #EFEBE2;text-align:right;">{{ \App\Support\Money::format($job->total) }}</td>
        </tr>
        <tr>
            <td style="padding:11px 0;font-weight:700;">Paid</td>
            <td style="padding:11px 0;text-align:right;font-weight:700;">{{ \App\Support\Money::format($payment->amount) }}</td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:12.5px;color:#6B6F66">
        Paid on {{ $payment->paid_at?->format('d M Y, H:i') }}.
        Your job reference is <b>{{ $job->reference }}</b> — quote it at the counter.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0;">
        <tr>
            <td style="border-radius:9px;background:#141712;">
                <a href="{{ route('jobs.show', $job) }}"
                   style="display:inline-block;padding:11px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13.5px;font-weight:600;color:#FFFFFF;text-decoration:none;">
                    Track the job
                </a>
            </td>
        </tr>
    </table>
@endsection
