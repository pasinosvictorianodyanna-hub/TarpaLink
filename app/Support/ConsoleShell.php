<?php

namespace App\Support;

use App\Models\Shop;
use App\Models\User;

/**
 * The console's navigation, built once.
 *
 * Every console screen asks this class for the sidebar rather than assembling
 * its own. That is the whole point: when the sidebar was drawn inside one Vue
 * page, adding a screen meant remembering to add a link, and every screen had
 * its own idea of which item was "current". Here the current item is a key the
 * controller passes in, and an unknown key is a bug rather than a silently
 * unhighlighted sidebar.
 */
final class ConsoleShell
{
    /** The icon set the console draws, named so the data stays free of markup. */
    public const ICONS = ['grid', 'inbox', 'board', 'table', 'box', 'tag', 'chart', 'doc'];

    /**
     * @return array{brand: string, badge: string, shops: list<array{id: int, name: string}>, shopId: int, user: array{initials: string, name: string, role: string}, groups: list<array{label: string, items: list<array{key: string, label: string, url: string, icon: string, active: bool, badge: ?int}>}>}
     */
    public static function for(User $user, Shop $shop, string $current, int $openJobs = 0): array
    {
        // Staff run the work; they do not set prices or see the shop's money.
        // Hiding the link is a courtesy — every one of those screens refuses the
        // request on its own as well.
        $manages = $user->canManagePrices();

        $groups = [[
            'label' => 'Operations',
            'items' => [
                self::item('dashboard', 'Dashboard', route('console.dashboard'), 'grid', $current),
                self::item('jobs', 'Jobs', route('console.jobs'), 'inbox', $current, $openJobs),
                self::item('board', 'Production board', route('console.board'), 'board', $current),
            ],
        ]];

        if ($manages) {
            $groups[] = [
                'label' => 'Catalogue',
                'items' => [
                    self::item('prices', 'Price matrix', route('console.prices'), 'table', $current),
                    self::item('services', 'Services', route('console.services'), 'box', $current),
                    self::item('discounts', 'Quantity discounts', route('console.discounts'), 'tag', $current),
                ],
            ];
        }

        $business = [self::item('reports', 'Reports', route('console.reports'), 'chart', $current)];

        if ($manages) {
            $business[] = self::item('billing', 'Billing', route('console.billing'), 'doc', $current);
        }

        $groups[] = ['label' => 'Business', 'items' => $business];

        return [
            'brand' => $shop->name,
            'badge' => 'Shop',
            // Only worth a control when there is a choice to make: a select with
            // one option is furniture.
            'shops' => $user->shops()
                ->orderBy('shops.name')
                ->get(['shops.id', 'shops.name'])
                ->map(fn ($option) => ['id' => $option->id, 'name' => $option->name])
                ->all(),
            'shopId' => $shop->id,
            'user' => [
                'initials' => self::initials($user->name),
                'name' => $user->name,
                'role' => $user->isShopStaff() ? 'Shop staff' : 'Shop owner',
            ],
            'groups' => $groups,
        ];
    }

    /**
     * @return array{key: string, label: string, url: string, icon: string, active: bool, badge: ?int}
     */
    private static function item(string $key, string $label, string $url, string $icon, string $current, ?int $badge = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
            'active' => $key === $current,
            // A zero badge is noise: "0" beside Jobs reads as a problem.
            'badge' => $badge ?: null,
        ];
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($parts)
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
