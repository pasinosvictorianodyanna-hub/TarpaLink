<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1rem;">
        <x-filament::section>
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;">{{ $monthLabel }} gross</div>
            <div style="font-size:1.6rem;font-weight:700;margin-top:.2rem;">{{ \App\Support\Money::format($monthGross) }}</div>
            <div style="font-size:.8rem;opacity:.6;">Captured payments this month</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;">Commission</div>
            <div style="font-size:1.6rem;font-weight:700;margin-top:.2rem;">{{ \App\Support\Money::format($monthCommission) }}</div>
            <div style="font-size:.8rem;opacity:.6;">Deducted, not charged to the customer</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;">Net this month</div>
            <div style="font-size:1.6rem;font-weight:700;margin-top:.2rem;">{{ \App\Support\Money::format($monthNet) }}</div>
            <div style="font-size:.8rem;opacity:.6;">Gross − commission</div>
        </x-filament::section>

        <x-filament::section>
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6;">Awaiting payout</div>
            <div style="font-size:1.6rem;font-weight:700;margin-top:.2rem;">{{ \App\Support\Money::format($pendingNet) }}</div>
            <div style="font-size:.8rem;opacity:.6;">Pending and scheduled rows</div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
