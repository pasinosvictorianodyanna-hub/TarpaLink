<?php

namespace App\Http\Controllers;

use App\Models\PageSection;
use App\Models\Shop;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featured = Shop::active()
            ->with(['services.tiers'])
            ->orderByDesc('rating')
            ->take(3)
            ->get();

        return view('home', [
            'hero' => PageSection::content('hero', [
                'eyebrow' => 'Print marketplace · Metro Manila',
                'headline' => "Custom printing,\npriced in seconds.",
                'subheadline' => 'Find a verified print shop, set your size and quantity — and see the exact price before you order.',
                'primary_label' => 'Find a print shop',
            ]),
            'banner' => PageSection::where('key', 'banner')->where('status', 'published')->first()?->content,
            'featured' => $featured,
            'shopCount' => Shop::active()->count(),
        ]);
    }
}
