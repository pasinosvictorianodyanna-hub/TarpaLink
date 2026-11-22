<?php

namespace App\Http\Controllers;

use App\Services\CmsClient;
use Illuminate\Contracts\View\View;

/**
 * The static pages the footer links to.
 *
 * Their copy lives in the CMS under `page.{slug}` rather than in Blade, so the
 * same CMS screen that edits the homepage hero edits "Privacy" — one content
 * surface, in a different application.
 */
class PageController extends Controller
{
    public function __construct(private readonly CmsClient $cms) {}

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

        $page = $this->cms->find('page.'.$slug);

        // A page that has been unpublished is gone, not an empty shell — the same
        // rule the homepage sections follow. The CMS never sends an unpublished
        // row, so "not published" and "does not exist" arrive here identically.
        abort_if($page === null, 404);

        return view('pages.show', [
            'slug' => $slug,
            'page' => $page,
            'supportEmail' => $this->cms->setting('support_email', 'hello@tarpalink.ph'),
        ]);
    }
}
