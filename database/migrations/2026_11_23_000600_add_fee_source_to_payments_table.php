<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the fee on a payment is the processor's number or ours.
 *
 * `payments.processing_fee` was written once, at checkout, from two literals in
 * the controller (2.5% + ₱15) and never checked against anything. The shop bears
 * that fee, so a wrong estimate is not a rounding error — it is the shop paying
 * a price we invented, and the platform quietly absorbing the difference because
 * the processor takes its cut from the platform's balance, not the shop's.
 *
 * The webhook now overwrites the estimate with the fee the provider reports.
 * This column is what keeps the two apart: without it an estimate and a fact are
 * the same row, and the ledger cannot say which numbers it can trust.
 *
 * Defaults to true because every existing row was written from the estimate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('fee_is_estimate')->default(true)->after('processing_fee');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('fee_is_estimate');
        });
    }
};
