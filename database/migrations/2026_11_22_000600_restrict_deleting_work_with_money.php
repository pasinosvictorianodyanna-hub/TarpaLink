<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A delete must not be able to reach money.
 *
 * Both cascades ended at the same place: `shops -> jobs -> payments`. Deleting a
 * shop destroyed its jobs, and with them every settled payment — the rows the
 * payout ledger and the platform's own commission are computed from. Nothing
 * warned; the admin screen has no delete button for a shop, but the database
 * was willing, and "the UI does not offer it" is not a rule.
 *
 * Both links are now RESTRICT, which makes the operating rule ("suspend, never
 * delete") something the database enforces rather than something everybody
 * remembers. A shop with order history cannot be removed; a job with a payment
 * against it cannot be removed. Abandoned jobs with no money on them still
 * delete cleanly, which is the case that actually needs deleting.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A payment is the record of money moving. Nothing upstream of it may
        // take it with it.
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
            $table->foreign('job_id')->references('id')->on('jobs')->restrictOnDelete();
        });

        // A job is a customer's order. A shop cannot be removed out from under
        // one, which also stops the cascade before it reaches `payments`.
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->foreign('shop_id')->references('id')->on('shops')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
            $table->foreign('job_id')->references('id')->on('jobs')->cascadeOnDelete();
        });
    }
};
