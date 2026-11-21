<?php

namespace App\Http\Controllers;

use App\Models\PageSection;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;

/**
 * The static pages the footer links to.
 *
 * Their copy lives in `page_sections` under `page.{slug}` rather than in Blade,
 * so the same CMS screen that edits the homepage hero edits "Privacy" — one
 * content surface, not a second one bolted on.
 */
class PageController extends Controller
{
    /**
     * Every slug the footer links to, and the only ones this route will answer.
     *
     * Declared here rather than in the route file so the constraint and the 404
     * cannot drift apart.
     */
    public const SLUGS = [
        'about',
        'contact',
        'privacy',
        'terms',
        'help',
        'pricing',
        'partners',
        'open-a-shop',
    ];

    public function __invoke(string $slug): View
    {
        abort_unless(in_array($slug, self::SLUGS, true), 404);

        $page = PageSection::where('key', 'page.'.$slug)
            ->where('status', 'published')
            ->first();

        // A page that has been unpublished is gone, not an empty shell — the same
        // rule the homepage sections follow.
        abort_if($page === null, 404);

        return view('pages.show', [
            'slug' => $slug,
            'page' => $page->content,
            'supportEmail' => SiteSetting::get('support_email', 'hello@tarpalink.ph'),
        ]);
    }
}
