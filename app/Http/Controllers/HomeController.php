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
            'banner' => $this->section('banner'),
            'testimonials' => $this->section('testimonials'),
            'faq' => $this->section('faq'),
            'featured' => $featured,
            'shopCount' => Shop::active()->count(),
        ]);
    }

    /**
     * A section's content, or null when it is missing or not published.
     *
     * Null is meaningful: the view omits the block entirely rather than
     * rendering an empty shell, so deleting a section hides it.
     */
    private function section(string $key): ?array
    {
        return PageSection::where('key', $key)
            ->where('status', 'published')
            ->first()
            ?->content;
    }
}
