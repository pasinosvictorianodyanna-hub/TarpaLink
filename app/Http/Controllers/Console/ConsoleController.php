<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Support\ConsoleShell;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The shop console.
 *
 * This is the shop side out of Filament entirely. Filament is the CMS, and the
 * CMS belongs on the admin side; a shop owner running their day is not editing
 * content, they are reading a queue. Keeping the dashboard here and the other
 * seven screens in the panel meant the shell changed every time they clicked,
 * which is the thing this base class exists to stop: every screen renders the
 * same layout, and the layout gets its nav from ConsoleShell.
 */
abstract class ConsoleController extends Controller
{
    /**
     * The shop this request operates on, or a 403.
     *
     * `User::canAccessPanel` was what admitted a shop owner to the Filament
     * panel. The console asks the same questions directly rather than inventing
     * a second rule that could disagree with it.
     */
    protected function shop(): Shop
    {
        $user = request()->user();
        $shop = $user?->currentShop();

        if ($user === null || ! $user->worksInShop() || $shop === null || ! $shop->isActive()) {
            throw new AccessDeniedHttpException('The shop console is for an active shop.');
        }

        return $shop;
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = request()->user();

        return $user;
    }

    protected function openJobs(Shop $shop): int
    {
        return $shop->jobs()->whereNotIn('status', ['collected', 'cancelled'])->count();
    }

    /**
     * Prices, the catalogue and the plan are the owner's, not the staff's.
     *
     * The sidebar hides those links for staff; hiding a link is a courtesy, not
     * a rule, so every one of those screens asks again here.
     */
    protected function requireManager(): void
    {
        abort_unless($this->user()->canManagePrices(), 403);
    }

    /**
     * Render a console screen: the shell, a heading, and whatever the page needs.
     *
     * @param  array{title: string, sub?: string}  $heading
     * @param  array<string, mixed>  $data
     * @param  list<array{label: string, url: string, style?: string}>  $actions
     */
    protected function page(string $current, array $heading, string $view, array $data = [], array $actions = []): View
    {
        $shop = $this->shop();

        return view($view, $data + [
            'shop' => $shop,
            'shell' => ConsoleShell::for($this->user(), $shop, $current, $this->openJobs($shop)),
            'heading' => $heading,
            'actions' => $actions,
        ]);
    }

    /** "2 jobs" / "1 job" — used in headings on more than one screen. */
    protected function plural(int $count, string $singular, ?string $plural = null): string
    {
        return $count.' '.($count === 1 ? $singular : ($plural ?? $singular.'s'));
    }
}
