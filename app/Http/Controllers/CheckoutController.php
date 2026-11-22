<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceUnavailable;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\PayMongo;
use App\Services\PriceCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckoutController extends Controller
{
    public function show(Request $request, Shop $shop, PriceCalculator $calculator): View|RedirectResponse
    {
        abort_unless($shop->status === 'active', 404);
        $shop->load(['services.options']);

        // A shop with nothing to sell has no checkout to show. Without this the
        // calculator is asked to price a null service and throws.
        abort_if($shop->services->isEmpty(), 404);

        $spec = $this->specFromRequest($request, $shop);

        try {
            $quote = $calculator->quote($shop, $spec);
        } catch (ServiceUnavailable $e) {
            // A stale or hand-edited link (bad service id, size outside every
            // tier) is a user problem, not a 500. Send them back to the builder
            // with the reason, the same way `store()` already does.
            return redirect()
                ->route('shops.builder', $shop)
                ->with('status', $e->getMessage());
        }

        $service = $shop->services->firstWhere('id', $spec['service_id']);

        return view('checkout', [
            'shop' => $shop,
            'service' => $service,
            'spec' => $spec,
            'quote' => $quote,
        ]);
    }

    public function store(Request $request, Shop $shop, PriceCalculator $calculator): RedirectResponse
    {
        abort_unless($shop->status === 'active', 404);

        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'width_in' => ['required', 'numeric', 'min:1', 'max:1000'],
            'height_in' => ['required', 'numeric', 'min:1', 'max:1000'],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'options' => ['nullable', 'string'],
            'payment_method' => ['required', 'in:gcash,maya,card,cod'],
            // Optional on the wire, always recorded on the job: the form marks
            // them required, and a client that omits them gets the account's own
            // details rather than a job with nobody's name on it.
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'pickup_person' => ['nullable', 'string', 'max:120'],
            'fulfillment' => ['nullable', 'in:pickup,delivery'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $spec = $this->specFromRequest($request, $shop);

        /*
         * A double-click used to create two jobs and two payment intents. The key
         * identifies who ordered what from which shop; Cache::add is atomic, so
         * two requests arriving together cannot both get through. Held for 30s
         * only — long enough to catch a double-click, short enough that a real
         * retry after a failure is not locked out (the failure releases it).
         */
        $key = 'checkout:'.sha1(json_encode([
            $request->user()->id,
            $shop->id,
            $spec,
            $data['payment_method'],
        ]));

        if (! Cache::add($key, true, now()->addSeconds(30))) {
            return back()
                ->withErrors(['checkout' => 'That order is already being placed. Check My jobs before trying again.'])
                ->withInput();
        }

        try {
            $quote = $calculator->quote($shop, $spec);
        } catch (ServiceUnavailable $e) {
            Cache::forget($key);

            return back()->withErrors(['size' => $e->getMessage()])->withInput();
        }

        try {
            /*
             * All writes, including the call to PayMongo, happen in one transaction.
             * Previously a failed intent threw after the job and payment had been
             * committed: the customer saw a 500, no way to pay, and retrying made
             * a second job. Now a failed intent rolls the whole order back.
             */
            $job = DB::transaction(function () use ($shop, $request, $data, $spec, $quote) {
                $job = Job::create([
                    'shop_id' => $shop->id,
                    'user_id' => $request->user()->id,
                    'contact_name' => $data['contact_name'] ?? $request->user()->name,
                    'contact_phone' => $data['contact_phone'] ?? $request->user()->phone,
                    'contact_email' => $data['contact_email'] ?? $request->user()->email,
                    'pickup_person' => $data['pickup_person'] ?? null,
                    'service_id' => $spec['service_id'],
                    'status' => 'quoted',
                    'spec' => $spec,
                    'subtotal' => $quote['subtotal'],
                    'discount' => $quote['discount'],
                    'total' => $quote['total'],
                    'matrix_version' => $quote['matrix_version'],
                    'pickup_city' => $shop->city,
                    'fulfillment' => $data['fulfillment'] ?? 'pickup',
                    'notes' => $data['notes'] ?? null,
                    'due_at' => Carbon::now()->addDays(2)->setTime(17, 0),
                ]);

                foreach ($quote['lines'] as $line) {
                    $job->lines()->create($line);
                }

                if ($data['payment_method'] !== 'cod') {
                    // Sandbox: no live charge. The webhook is the only thing that
                    // marks this paid — nothing in checkout may set status to paid.
                    $payment = Payment::create([
                        'job_id' => $job->id,
                        'provider' => 'paymongo',
                        'intent_id' => 'pending-'.$job->reference,
                        'amount' => $quote['total'],
                        'processing_fee' => (int) round($quote['total'] * 0.025) + 1500,
                        'commission' => (int) round($quote['total'] * $shop->commission_rate / 10000),
                        'status' => 'pending',
                    ]);

                    $intent = app(PayMongo::class)->createIntent($job, $payment);

                    $payment->update(['intent_id' => $intent['id']]);
                }

                return $job;
            });
        } catch (Throwable $e) {
            Cache::forget($key);
            report($e);

            return back()
                ->withErrors(['checkout' => 'We could not complete the order. Nothing was charged — please try again.'])
                ->withInput();
        }

        return redirect()
            ->route('jobs.show', $job)
            ->with('status', 'Job '.$job->reference.' created. The shop will send a proof before printing.');
    }

    /** @return array{service_id:int, width_in:float, height_in:float, qty:int, options:array} */
    private function specFromRequest(Request $request, Shop $shop): array
    {
        $options = [];

        foreach (array_filter(explode(',', (string) $request->input('options', ''))) as $pair) {
            [$id, $qty] = array_pad(explode(':', $pair), 2, 0);
            $options[] = ['id' => (int) $id, 'qty' => (float) $qty];
        }

        return [
            'service_id' => (int) $request->input('service_id', $shop->services->first()?->id),
            'width_in' => (float) $request->input('width_in', 36),
            'height_in' => (float) $request->input('height_in', 48),
            'qty' => max(1, (int) $request->input('qty', 1)),
            'options' => $options,
        ];
    }
}
