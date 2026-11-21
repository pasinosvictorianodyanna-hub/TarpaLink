@include('filament.shop.partials.theme')

<x-filament-panels::page>
    <div class="tl-wrap" style="display:grid;gap:14px">

        <div class="tl-card" style="padding:14px 16px">
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="q"
                    placeholder="Search section or key"
                    style="flex:1;min-width:220px;height:36px;padding:0 12px;border:1px solid var(--tl-rule);border-radius:999px;background:#fff;font-size:12.5px;color:var(--tl-ink)"
                >

                <select wire:model.live="status"
                        style="height:36px;padding:0 10px;border:1px solid var(--tl-rule);border-radius:999px;background:#fff;font-size:12px">
                    @foreach ($this->statusOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if ($this->hasFilters())
                    <button type="button" wire:click="clearFilters"
                            style="height:36px;padding:0 12px;border:1px solid var(--tl-rule);border-radius:999px;background:none;font-size:12px;font-weight:600;cursor:pointer">
                        Clear
                    </button>
                @endif

                <span class="meta" style="margin-left:auto">{{ $this->total() }} {{ $this->total() === 1 ? 'section' : 'sections' }}</span>
            </div>
        </div>

        <div class="tl-card">
            @php($sections = $this->sections())

            @if ($sections->isEmpty())
                <div class="tl-empty">No sections match these filters.</div>
            @else
                <table class="tl-tbl">
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>Key</th>
                            <th>What it says</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sections as $section)
                            <tr>
                                <td>
                                    <a href="{{ \App\Filament\Admin\Resources\PageSections\PageSectionResource::getUrl('edit', ['record' => $section]) }}"
                                       style="font-weight:600;text-decoration:underline;text-underline-offset:2px">{{ $section->label }}</a>
                                </td>

                                <td><code style="font-size:11.5px;color:var(--tl-muted)">{{ $section->key }}</code></td>

                                <td style="max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--tl-muted)">
                                    {{ $this->preview($section) }}
                                </td>

                                <td>
                                    <span class="tl-pill {{ $this->pillFor($section->status) }}">{{ ucfirst($section->status) }}</span>
                                </td>

                                <td style="white-space:nowrap;color:var(--tl-muted)">{{ $section->updated_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
