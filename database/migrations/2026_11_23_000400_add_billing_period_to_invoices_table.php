<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invoice needs to know which month it is for, and when it is late.
 *
 * `invoices` was written for the one thing that existed: a shop switching plan
 * and being billed once. That is not a subscription. A subscription bills the
 * same amount every month, so the invoice has to say **which** month — otherwise
 * "does this shop already have this month's invoice?" is unanswerable and the
 * command that issues them either duplicates or skips.
 *
 * `due_at` is stored rather than derived so the terms can change without
 * silently restating every historical invoice's lateness. Overdue is still
 * *computed* from it — `open` and past due — because a status column that a
 * nightly job has to advance is a status column that is wrong every night
 * between the deadline and the run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('amount');
            $table->date('period_end')->nullable()->after('period_start');
            $table->timestamp('due_at')->nullable()->after('issued_at');

            // Who recorded the money. A subscription invoice is usually settled
            // by GCash or a bank transfer, so "paid" is a person's claim and
            // should carry their name.
            $table->foreignId('paid_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropColumn(['period_start', 'period_end', 'due_at', 'paid_by']);
        });
    }
};
