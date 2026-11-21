<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who the job is actually for, and how they get it.
 *
 * Checkout has always shown a name and a phone field — and thrown both away.
 * The job recorded the account that placed it and nothing else, so a shop
 * preparing a counter order had no idea whose name to write on the bag. These
 * columns are the contact the customer typed, which may differ from the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('user_id');
            $table->string('contact_phone', 32)->nullable()->after('contact_name');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->string('pickup_person')->nullable()->after('contact_email');
            $table->string('fulfillment', 16)->default('pickup')->after('pickup_city');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_phone', 'contact_email', 'pickup_person', 'fulfillment']);
        });
    }
};
