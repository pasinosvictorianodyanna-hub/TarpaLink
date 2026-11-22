@extends('layouts.console')

@section('page')
    @php($isNew = ! $band->exists)

    <form
        method="POST"
        action="{{ $isNew ? route('console.discounts.store') : route('console.discounts.update', $band) }}"
        style="display:grid;gap:16px;max-width:520px"
    >
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <div class="fil-card">
            <div style="padding:16px;display:grid;gap:14px">
                <label style="font-size:11px;color:#6B7280">
                    When quantity reaches
                    <input type="number" name="min_qty" min="1" value="{{ old('min_qty', $band->min_qty) }}" required
                           style="display:block;width:100%;height:34px;margin-top:4px;padding:0 10px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px">
                    <span style="display:block;margin-top:3px">Bands apply at or above this quantity, on the whole job.</span>
                </label>

                <label style="font-size:11px;color:#6B7280">
                    Discount
                    <input type="number" name="percent" min="1" max="90" value="{{ old('percent', $band->percent) }}" required
                           style="display:block;width:100%;height:34px;margin-top:4px;padding:0 10px;border:1px solid #E5E7EB;border-radius:7px;font:inherit;font-size:12.5px">
                    <span style="display:block;margin-top:3px">A percentage of the subtotal, before the platform commission.</span>
                </label>
            </div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end">
            <a class="fbtn fbtn-ghost" href="{{ route('console.discounts') }}">Cancel</a>
            <button class="fbtn fbtn-primary" type="submit">{{ $isNew ? 'Add band' : 'Save band' }}</button>
        </div>
    </form>
@endsection
