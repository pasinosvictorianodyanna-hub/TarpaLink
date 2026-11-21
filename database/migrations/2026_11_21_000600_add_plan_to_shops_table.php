<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Subscription tier. The plans table itself arrives with the billing
            // task — the rate lives here now so no payout code hardcodes a percent.
            $table->string('plan', 24)->default('starter')->after('status');

            // Platform take rate in basis points, so 500 = 5.00%. Basis points
            // keep 1.5% exact; a float would not.
            $table->unsignedSmallInteger('commission_rate')->default(500)->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['plan', 'commission_rate']);
        });
    }
};
