<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The directory design has a filter rail: turnaround and service options.
 * None of that existed — `accepts_rush` was the only signal, and it answers a
 * narrower question than "same day, 24 hours, or 2–3 days".
 *
 * These are shop facts, not per-job facts, so they belong on the shop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedSmallInteger('turnaround_hours')->default(48)->after('accepts_rush');
            $table->boolean('offers_delivery')->default(false)->after('turnaround_hours');
            $table->boolean('offers_design')->default(false)->after('offers_delivery');
            $table->boolean('accepts_gcash')->default(false)->after('offers_design');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['turnaround_hours', 'offers_delivery', 'offers_design', 'accepts_gcash']);
        });
    }
};
