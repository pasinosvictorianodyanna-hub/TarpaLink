<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopApproval;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The engine's administrative surface, for the CMS.
 *
 * The CMS is where an administrator works; this is where the work actually
 * happens. Every action here is the same service the engine's own panel calls,
 * so approving a shop from the CMS and approving it from the engine cannot
 * produce two different outcomes — there is one implementation, reached two
 * ways.
 *
 * Nothing here trusts the caller beyond its token. `actor` is resolved to a real
 * administrator so the audit trail still names a person, and it is optional: a
 * call without one records the change as having no named actor rather than
 * refusing to work.
 */
class AdminApiController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'application' => config('app.name'),
            'time' => now()->toIso8601String(),
            'shops' => Shop::query()->count(),
            'jobs' => Job::query()->count(),
        ]);
    }

    /**
     * Everything the CMS hub needs, in one call.
     *
     * The hub shows four figures from three different tables. Fetching them
     * separately meant four round trips before the page could paint, and four
     * chances for one of them to time out and leave the page half-filled. One
     * endpoint, one number per tile, one failure mode.
     */
    public function summary(): JsonResponse
    {
        $month = [now()->startOfMonth(), now()->endOfMonth()];

        $held = (int) Payment::query()->awaitingPayout()
            ->selectRaw('COALESCE(SUM(amount - commission), 0) as held')
            ->value('held');

        return response()->json([
            'shops' => [
                'pending' => Shop::query()->where('status', 'pending')->count(),
                'active' => Shop::query()->where('status', 'active')->count(),
                'suspended' => Shop::query()->where('status', 'suspended')->count(),
                'total' => Shop::query()->count(),
            ],
            'users' => [
                'admin' => User::query()->where('role', 'admin')->count(),
                'shop_owner' => User::query()->where('role', 'shop_owner')->count(),
                'customer' => User::query()->where('role', 'customer')->count(),
                'total' => User::query()->count(),
            ],
            'payouts' => [
                'waiting' => Payment::query()->awaitingPayout()->count(),
                'held_centavos' => $held,
                'held' => Money::short($held),
            ],
            'jobs' => [
                'this_month' => Job::query()->whereBetween('created_at', $month)->count(),
                'total' => Job::query()->count(),
            ],
            'gmv' => [
                'this_month_centavos' => (int) Payment::query()
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', $month)
                    ->sum('amount'),
            ],
        ]);
    }

    public function shops(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $query = Shop::query()
            ->withCount('services')
            // The queue first, exactly as the panel sorts it: a pending shop is
            // the only row on this screen that needs a decision today.
            ->orderByRaw("case status when 'pending' then 0 when 'active' then 1 else 2 end")
            ->orderBy('name');

        if (in_array($status, ['pending', 'active', 'suspended'], true)) {
            $query->where('status', $status);
        }

        return response()->json([
            'shops' => $query->limit(200)->get()->map(fn (Shop $shop): array => $this->presentShop($shop))->all(),
            'counts' => [
                'pending' => Shop::query()->where('status', 'pending')->count(),
                'active' => Shop::query()->where('status', 'active')->count(),
                'suspended' => Shop::query()->where('status', 'suspended')->count(),
                'total' => Shop::query()->count(),
            ],
        ]);
    }

    public function approveShop(Request $request, Shop $shop): JsonResponse
    {
        app(ShopApproval::class)->approve($shop, $this->actor($request));

        return response()->json(['shop' => $this->presentShop($shop->fresh())]);
    }

    public function suspendShop(Request $request, Shop $shop): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        app(ShopApproval::class)->suspend($shop, $validated['reason'], $this->actor($request));

        return response()->json(['shop' => $this->presentShop($shop->fresh())]);
    }

    public function users(Request $request): JsonResponse
    {
        $role = $request->query('role');

        $query = User::query()->orderBy('name');

        if (in_array($role, ['admin', 'shop_owner', 'customer'], true)) {
            $query->where('role', $role);
        }

        return response()->json([
            'users' => $query->limit(200)->get()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'joined' => $user->created_at?->toDateString(),
            ])->all(),
            'counts' => [
                'admin' => User::query()->where('role', 'admin')->count(),
                'shop_owner' => User::query()->where('role', 'shop_owner')->count(),
                'customer' => User::query()->where('role', 'customer')->count(),
            ],
        ]);
    }

    public function payouts(): JsonResponse
    {
        $payments = Payment::query()
            ->awaitingPayout()
            ->with(['job.shop'])
            ->orderBy('paid_at')
            ->limit(200)
            ->get();

        return response()->json([
            'payouts' => $payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'shop' => $payment->job?->shop?->name,
                'job' => $payment->job?->reference,
                'gross' => Money::format($payment->amount),
                'commission' => Money::format($payment->commission),
                'net' => Money::format($payment->net()),
                'net_centavos' => $payment->net(),
                'paid_at' => $payment->paid_at?->toDateString(),
            ])->all(),
            'held_centavos' => (int) $payments->sum(fn (Payment $p): int => $p->net()),
            'held' => Money::format((int) $payments->sum(fn (Payment $p): int => $p->net())),
        ]);
    }

    public function releasePayout(Payment $payment): JsonResponse
    {
        // Releasing is a record, not a transfer: it states that the shop has been
        // paid, and refusing to do it twice is what keeps the ledger honest.
        if ($payment->payout_status === 'paid') {
            return response()->json(['message' => 'This payout has already been released.'], 409);
        }

        $payment->forceFill([
            'payout_status' => 'paid',
            'payout_date' => now()->toDateString(),
        ])->save();

        return response()->json([
            'released' => true,
            'net' => Money::format($payment->net()),
        ]);
    }

    /** @return array<string, mixed> */
    private function presentShop(Shop $shop): array
    {
        $owner = $shop->staff()->wherePivot('role', 'owner')->first();

        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'slug' => $shop->slug,
            'city' => $shop->city,
            'status' => $shop->status,
            'status_reason' => $shop->status_reason,
            'services' => $shop->services_count ?? $shop->services()->count(),
            'rating' => (float) $shop->rating,
            'owner' => $owner === null ? null : ['name' => $owner->name, 'email' => $owner->email],
            'applied' => $shop->created_at?->toDateString(),
        ];
    }

    private function actor(Request $request): ?User
    {
        $email = trim((string) $request->input('actor'));

        if ($email === '') {
            return null;
        }

        return User::query()
            ->where('email', $email)
            ->where('role', 'admin')
            ->first();
    }
}
