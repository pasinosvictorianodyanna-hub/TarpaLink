<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A refund needs two numbers, not a status change.
 *
 * `payments.status` has carried a `refunded` value since #11 and nothing ever
 * wrote it, because a status alone cannot say how much came back. A partial
 * refund is the normal case — the published policy promises a full refund only
 * before the proof is approved, and says a cancellation after that "may be
 * partial" — so the amount is the fact and the status is derived from it.
 *
 * `commission_refunded` is stored rather than computed on read for the same
 * reason `commission` is stored rather than recomputed from the plan: it is a
 * snapshot of a decision, and a shop that changes tier tomorrow must not see
 * last month's reversal move.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // How much of `amount` went back to the customer, cumulatively.
            $table->integer('refunded_amount')->default(0)->after('amount');

            // The part of `commission` we gave up, cumulatively. Reverses
            // proportionally, because we did not earn a commission on money we
            // handed back.
            $table->integer('commission_refunded')->default(0)->after('commission');

            $table->timestamp('refunded_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['refunded_amount', 'commission_refunded', 'refunded_at']);
        });
    }
};
