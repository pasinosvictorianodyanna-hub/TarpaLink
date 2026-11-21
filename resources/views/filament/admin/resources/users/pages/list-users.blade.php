@include('filament.shop.partials.theme')

<x-filament-panels::page>
    <div class="tl-wrap" style="display:grid;gap:14px">

        <div class="tl-card" style="padding:14px 16px">
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="q"
                    placeholder="Search name or email"
                    style="flex:1;min-width:220px;height:36px;padding:0 12px;border:1px solid var(--tl-rule);border-radius:999px;background:#fff;font-size:12.5px;color:var(--tl-ink)"
                >

                <select wire:model.live="role"
                        style="height:36px;padding:0 10px;border:1px solid var(--tl-rule);border-radius:999px;background:#fff;font-size:12px">
                    @foreach ($this->roleOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if ($this->hasFilters())
                    <button type="button" wire:click="clearFilters"
                            style="height:36px;padding:0 12px;border:1px solid var(--tl-rule);border-radius:999px;background:none;font-size:12px;font-weight:600;cursor:pointer">
                        Clear
                    </button>
                @endif

                <span class="meta" style="margin-left:auto">{{ $this->total() }} {{ $this->total() === 1 ? 'user' : 'users' }}</span>
            </div>
        </div>

        <div class="tl-card">
            @php($users = $this->users())

            @if ($users->isEmpty())
                <div class="tl-empty">No users match these filters.</div>
            @else
                <table class="tl-tbl">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Shop</th>
                            <th>Joined</th>
                            <th style="text-align:right">Jobs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:9px">
                                        <span style="width:28px;height:28px;border-radius:999px;background:var(--tl-paper-2,#EFEBE0);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700">
                                            {{ $this->initials($user) }}
                                        </span>
                                        <span>
                                            <div style="font-weight:600">{{ $user->name }}</div>
                                            <div style="font-size:11px;color:var(--tl-muted)">{{ $user->email }}</div>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span class="tl-pill {{ $this->pillFor($user->role) }}">{{ $this->roleLabel($user->role) }}</span>
                                </td>

                                <td style="color:var(--tl-muted)">{{ $user->shops->first()?->name ?? '—' }}</td>

                                <td style="white-space:nowrap">{{ $user->created_at?->format('d M Y') }}</td>

                                <td class="tl-amt">{{ $user->jobs_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div style="padding:12px 16px;border-top:1px solid var(--tl-rule)">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
