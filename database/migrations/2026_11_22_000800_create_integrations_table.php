<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credentials that can be changed without a redeploy.
 *
 * Deliberately not `site_settings`. That table holds public copy — a headline,
 * a support address — and it is edited through a CMS screen that lists every
 * row. Putting an API key beside a headline means the next person who exports
 * the content table exports the keys with it, and the screen that shows all
 * settings shows the secret too.
 *
 * One row per provider, holding an encrypted JSON blob rather than a column per
 * key: the fields differ by provider, and a schema migration for each new one
 * is not a good trade for five short strings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->unique();
            // Ciphertext, not JSON — `encrypted:array` on the model handles both
            // directions, so nothing in the database ever holds a readable key.
            $table->text('settings')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
