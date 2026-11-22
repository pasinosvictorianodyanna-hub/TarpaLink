@extends('layouts.console')

@section('page')
    <div class="fil-card">
        <div class="ftbl-wrap">
            <table class="ftbl">
                <thead><tr><th>From quantity</th><th>Discount</th><th>Applies to</th><th></th></tr></thead>
                <tbody>
                    @forelse ($bands as $band)
                        <tr>
                            <td class="t">{{ $band->min_qty }} units or more</td>
                            <td style="font-variant-numeric:tabular-nums"><span class="fb fb-g">{{ $band->percent }}% off</span></td>
                            <td style="color:#6B7280">the whole job, not the extra units</td>
                            <td class="r" style="white-space:nowrap">
                                <a class="fb fb-y" href="{{ route('console.discounts.edit', $band) }}">Edit</a>

                                <form method="POST" action="{{ route('console.discounts.destroy', $band) }}" style="display:inline" onsubmit="return confirm('Remove this band?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="fb fb-r" style="cursor:pointer">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="color:#6B7280">No bands. Every job is charged the full matrix price.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="fil-foot">
            <span>Bands are checked highest first: a 100-unit order takes the best band it qualifies for, never two.</span>
        </div>
    </div>
@endsection
