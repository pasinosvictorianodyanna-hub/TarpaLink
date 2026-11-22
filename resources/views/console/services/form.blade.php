@extends('layouts.console')

@section('page')
    @php($isNew = ! $service->exists)

    <form
        method="POST"
        action="{{ $isNew ? route('console.services.store') : route('console.services.update', $service) }}"
        style="display:grid;gap:16px"
    >
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <div class="fil-card">
            <div class="fil-toolbar"><b style="font-size:12.5px">The service</b></div>

            <div style="padding:14px 16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px">
                <label style="font-size:11px;color:#6B7280">
                    Name
                    <input name="name" value="{{ old('name', $service->name) }}" required maxlength="255"
                           style="display:block;width:100%;height:34px;margin-top:4px;padding:0 10px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px">
                </label>

                <label style="font-size:11px;color:#6B7280">
                    Slug
                    <input name="slug" value="{{ old('slug', $service->slug) }}" required maxlength="255"
                           style="display:block;width:100%;height:34px;margin-top:4px;padding:0 10px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px">
                    <span style="display:block;margin-top:3px">Used in the storefront URL. Changing it breaks old links.</span>
                </label>

                <label style="font-size:11px;color:#6B7280">
                    Priced by
                    <select name="unit" style="display:block;width:100%;height:34px;margin-top:4px;padding:0 9px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px;background:#fff">
                        <option value="sqft" @selected(old('unit', $service->unit) === 'sqft')>Per square foot (tarpaulin)</option>
                        <option value="pc" @selected(old('unit', $service->unit) === 'pc')>Per piece (stickers, flyers, shirts)</option>
                    </select>
                    <span style="display:block;margin-top:3px">Decides whether a tier rate is multiplied by area or by quantity.</span>
                </label>

                <label style="font-size:11px;color:#6B7280">
                    Order
                    <input type="number" name="sort" min="0" max="999" value="{{ old('sort', $service->sort ?? 0) }}"
                           style="display:block;width:100%;height:34px;margin-top:4px;padding:0 10px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px">
                </label>

                <label style="font-size:11px;color:#6B7280;grid-column:1/-1">
                    Blurb
                    <textarea name="blurb" rows="2" maxlength="500"
                              style="display:block;width:100%;margin-top:4px;padding:8px 10px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px">{{ old('blurb', $service->blurb) }}</textarea>
                </label>

                <label style="font-size:12px;display:flex;align-items:center;gap:7px;grid-column:1/-1">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $service->active ?? true))>
                    Live on the storefront
                    <span style="color:#6B7280;font-size:11px">— hidden services keep their history but cannot be ordered.</span>
                </label>
            </div>
        </div>

        <div class="fil-card">
            <div class="fil-toolbar">
                <b style="font-size:12.5px">Size tiers</b>
                <span style="font-size:11px;color:#6B7280">{{ $service->unit === 'pc' ? 'rate per piece' : 'rate per square foot' }}</span>
                <button type="button" class="fbtn fbtn-ghost" style="margin-left:auto" onclick="addRow('tier')">Add tier</button>
            </div>

            <div class="ftbl-wrap">
                <table class="ftbl">
                    <thead>
                        <tr><th>Tier label</th><th>From (in)</th><th>Up to (in)</th><th>Rate ₱</th><th>Order</th><th></th></tr>
                    </thead>
                    <tbody id="tier-body">
                        @foreach ($tiers as $tier)
                            <tr>
                                <td>
                                    <input type="hidden" name="tiers[{{ $loop->index }}][id]" value="{{ $tier->id }}">
                                    <input name="tiers[{{ $loop->index }}][label]" value="{{ $tier->label }}" required
                                           style="width:100%;min-width:120px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px">
                                </td>
                                <td><input type="number" step="0.01" name="tiers[{{ $loop->index }}][min_width]" value="{{ $tier->min_width }}" style="width:84px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
                                <td><input type="number" step="0.01" name="tiers[{{ $loop->index }}][max_width]" value="{{ $tier->max_width }}" placeholder="no limit" style="width:90px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
                                <td><input type="number" step="0.01" name="tiers[{{ $loop->index }}][unit_price]" value="{{ \App\Support\Money::toPesos($tier->unit_price) }}" required style="width:96px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
                                <td><input type="number" name="tiers[{{ $loop->index }}][sort]" value="{{ $tier->sort }}" style="width:64px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
                                <td class="r"><button type="button" class="fbtn fbtn-ghost" onclick="this.closest('tr').remove()">Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="fil-foot">
                <span>The tier is chosen by the <strong>long edge</strong>, so “up to” means the longest side. A service with no tiers cannot be quoted.</span>
            </div>
        </div>

        <div class="fil-card">
            <div class="fil-toolbar">
                <b style="font-size:12.5px">Finishing add-ons</b>
                <button type="button" class="fbtn fbtn-ghost" style="margin-left:auto" onclick="addRow('option')">Add add-on</button>
            </div>

            <div class="ftbl-wrap">
                <table class="ftbl">
                    <thead><tr><th>Option</th><th>Charged</th><th>Price ₱</th><th>Order</th><th></th></tr></thead>
                    <tbody id="option-body">
                        @foreach ($options as $option)
                            <tr>
                                <td>
                                    <input type="hidden" name="options[{{ $loop->index }}][id]" value="{{ $option->id }}">
                                    <input name="options[{{ $loop->index }}][name]" value="{{ $option->name }}" required
                                           style="width:100%;min-width:130px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px">
                                </td>
                                <td>
                                    <select name="options[{{ $loop->index }}][unit]" style="height:30px;padding:0 6px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px;background:#fff">
                                        @foreach (['pc' => 'per piece', 'sqft' => 'per sq ft', 'ft' => 'per foot'] as $value => $label)
                                            <option value="{{ $value }}" @selected($option->unit === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" name="options[{{ $loop->index }}][price]" value="{{ \App\Support\Money::toPesos($option->price) }}" required style="width:96px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
                                <td><input type="number" name="options[{{ $loop->index }}][sort]" value="{{ $option->sort }}" style="width:64px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
                                <td class="r"><button type="button" class="fbtn fbtn-ghost" onclick="this.closest('tr').remove()">Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end">
            <a class="fbtn fbtn-ghost" href="{{ route('console.services') }}">Cancel</a>
            <button class="fbtn fbtn-primary" type="submit">{{ $isNew ? 'Create service' : 'Save service' }}</button>
        </div>
    </form>

    <template id="tier-row">
        <tr>
            <td><input name="tiers[__i__][label]" required placeholder="Large (≤96in)" style="width:100%;min-width:120px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
            <td><input type="number" step="0.01" name="tiers[__i__][min_width]" value="0" style="width:84px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
            <td><input type="number" step="0.01" name="tiers[__i__][max_width]" placeholder="no limit" style="width:90px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
            <td><input type="number" step="0.01" name="tiers[__i__][unit_price]" required style="width:96px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
            <td><input type="number" name="tiers[__i__][sort]" value="0" style="width:64px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
            <td class="r"><button type="button" class="fbtn fbtn-ghost" onclick="this.closest('tr').remove()">Remove</button></td>
        </tr>
    </template>

    <template id="option-row">
        <tr>
            <td><input name="options[__i__][name]" required style="width:100%;min-width:130px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
            <td>
                <select name="options[__i__][unit]" style="height:30px;padding:0 6px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px;background:#fff">
                    <option value="pc">per piece</option>
                    <option value="sqft">per sq ft</option>
                    <option value="ft">per foot</option>
                </select>
            </td>
            <td><input type="number" step="0.01" name="options[__i__][price]" required style="width:96px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
            <td><input type="number" name="options[__i__][sort]" value="0" style="width:64px;height:30px;padding:0 8px;border:1px solid #E5E7EB;border-radius:6px;font:inherit;font-size:12px"></td>
            <td class="r"><button type="button" class="fbtn fbtn-ghost" onclick="this.closest('tr').remove()">Remove</button></td>
        </tr>
    </template>

    <script>
        // A counter, not the row count: removing a middle row must not make the
        // next one reuse an index that is still in the form.
        var rowSeq = 1000;

        function addRow(kind) {
            var template = document.getElementById(kind + '-row');
            var body = document.getElementById(kind + '-body');
            var html = template.innerHTML.replaceAll('__i__', String(rowSeq++));

            var row = document.createElement('tr');
            row.innerHTML = html;
            body.appendChild(row);
        }
    </script>
@endsection
