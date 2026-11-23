<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceUnavailable;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Shop;
use App\Services\PayMongo;
use App\Services\PriceCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'props' => $this->props($request, $shop, $service, $spec, $quote),
        ]);
    }

    /**
     * Everything the checkout island renders, decided here rather than in Vue.
     *
     * The component is given numbers and labels; it is not given a price it can
     * recalculate, a discount it can apply, or a service it can look up. That is
     * the whole point of the split — "Vue renders, Laravel decides" — and it is
     * what makes the server authoritative on price rather than merely the last
     * to speak.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function props(Request $request, Shop $shop, ?Service $service, array $spec, array $quote): array
    {
        $user = $request->user();

        return [
            'authenticated' => $user !== null,
            'user' => [
                'name' => $user?->name,
                'phone' => $user?->phone,
                'email' => $user?->email,
            ],
            'shop' => [
                'name' => $shop->name,
                'address' => $shop->address,
                'city' => $shop->city,
                'distance' => $shop->distanceLabel(),
                'gradient' => $shop->coverGradient(),
                'offersDelivery' => (bool) $shop->offers_delivery,
                'rating' => number_format($shop->rating, 1),
                'jobsDone' => (int) $shop->jobs_done,
                'turnaround' => $shop->turnaroundLabel(),
                'initials' => $this->initials($shop->name),
                'readyAt' => $shop->estimatedReadyAt()->format('D, d M · g:i A'),
            ],
            'service' => ['name' => $service?->name ?? 'Job'],
            // `options` is sent to the server as a string, exactly as the old
            // form posted it: `12:2,15:1` is the wire format the calculator
            // already parses, and changing it here would mean two parsers.
            'spec' => [
                'service_id' => $spec['service_id'],
                'width_in' => $spec['width_in'],
                'height_in' => $spec['height_in'],
                'qty' => $spec['qty'],
                'options' => collect($spec['options'])->map(fn (array $o): string => $o['id'].':'.$o['qty'])->implode(','),
                'sizeLabel' => $this->inches($spec['width_in']).'×'.$this->inches($spec['height_in']).' in',
            ],
            'quote' => [
                'total' => $quote['total'],
                'tier' => $quote['tier'],
                'discount' => $quote['discount'],
                'lines' => array_map(fn (array $line): array => [
                    'label' => $line['label'],
                    'qty' => rtrim(rtrim(number_format($line['qty'], 2), '0'), '.'),
                    'amount' => $line['amount'],
                ], $quote['lines']),
            ],
            'urls' => [
                'store' => route('checkout.store', $shop),
                'signIn' => route('login'),
                'edit' => route('shops.builder', [
                    'shop' => $shop,
                    'service_id' => $spec['service_id'],
                    'width_in' => $spec['width_in'],
                    'height_in' => $spec['height_in'],
                    'qty' => $spec['qty'],
                ]),
            ],
            'methods' => [
                ['value' => 'gcash', 'label' => 'GCash'],
                ['value' => 'maya', 'label' => 'Maya'],
                ['value' => 'card', 'label' => 'Card'],
                ['value' => 'cod', 'label' => 'Cash at pickup'],
            ],
        ];
    }

    /** "Inkwell Press" → "IP", for the avatar the shop card shows. */
    private function initials(string $name): string
    {
        return str($name)
            ->explode(' ')
            ->filter(fn (string $part): bool => (bool) preg_match('/^\p{L}/u', $part))
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /** Whole inches print without the `.0` a float cast would leave behind. */
    private function inches(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
    }

    public function store(Request $request, Shop $shop, PriceCalculator $calculator): RedirectResponse|JsonResponse
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
            // What the page showed the customer, in centavos. Not used to price
            // anything — used to refuse a charge they did not agree to.
            'expected_total' => ['nullable', 'integer', 'min:0'],
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
            return $this->fail(
                $request,
                'checkout',
                'That order is already being placed. Check My jobs before trying again.',
                409,
            );
        }

        try {
            $quote = $calculator->quote($shop, $spec);
        } catch (ServiceUnavailable $e) {
            Cache::forget($key);

            return $this->fail($request, 'size', $e->getMessage());
        }

        /*
         * The price the customer was shown has to be the price they are charged.
         *
         * The server prices the order from the shop's matrix — it never trusts a
         * number from the browser. But a matrix can change while the page is
         * open, and quietly charging the new total is how somebody ends up
         * paying an amount they never agreed to. So the page sends back what it
         * displayed, and a disagreement stops the order before anything is
         * written.
         */
        if (isset($data['expected_total']) && (int) $data['expected_total'] !== (int) $quote['total']) {
            Cache::forget($key);

            return $this->fail(
                $request,
                'total',
                'The price changed while you were checking out. Review the new total and confirm again.',
                409,
                ['total' => $quote['total'], 'tier' => $quote['tier']],
            );
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
                    // The shop's published turnaround, not a constant: see
                    // Shop::estimatedReadyAt().
                    'due_at' => $shop->estimatedReadyAt(),
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
                        // An estimate until the processor reports its actual
                        // fee with the settled payment. The shop bears this, so
                        // the rate lives in config rather than in this line.
                        'processing_fee' => Payment::estimateFee($quote['total']),
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

            return $this->fail(
                $request,
                'checkout',
                'We could not complete the order. Nothing was charged — please try again.',
                500,
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'job' => ['reference' => $job->reference, 'total' => $job->total],
                'redirect' => route('jobs.show', $job),
            ], 201);
        }

        return redirect()
            ->route('jobs.show', $job)
            ->with('status', 'Job '.$job->reference.' created. Send your design next — '.$shop->name.' cannot start without it.');
    }

    /**
     * One failure shape for both callers.
     *
     * A browser form wants a redirect back with an error bag; the island wants
     * a status code and field errors it can put under the right input. Returning
     * a redirect to a fetch() call would arrive as a 302 whose body the client
     * cannot read, which is how "the button does nothing" happens.
     *
     * @param  array<string, mixed>  $extra
     */
    private function fail(
        Request $request,
        string $field,
        string $message,
        int $status = 422,
        array $extra = [],
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json(
                ['message' => $message, 'errors' => [$field => [$message]]] + $extra,
                $status,
            );
        }

        return back()->withErrors([$field => $message])->withInput();
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
