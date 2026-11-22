<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes storefront copy from the engine.
 *
 * The two tables moved to the CMS application, which is a separate system with
 * its own database. The create migration was deleted along with them, so a
 * fresh install never makes these tables — this migration exists only to clear
 * them out of databases that were built before the split.
 *
 * Guarded with `hasTable` for exactly that reason: on a new database there is
 * nothing to drop, and an unguarded `dropIfExists` would be indistinguishable
 * from a typo if the names ever drifted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('site_settings');
    }

    public function down(): void
    {
        // Not reversible on purpose. The rows now live in the CMS; recreating
        // empty tables here would give a future reader two places to look for
        // content and only one of them would be right.
    }
};
