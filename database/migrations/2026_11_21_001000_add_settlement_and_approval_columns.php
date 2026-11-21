<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two lifecycles that were missing.
 *
 * A payment has always had one status (pending → paid). Settlement is a second,
 * separate lifecycle: the customer paid weeks ago, but the shop has not been
 * paid out yet. Folding both into one column would lose that distinction, so
 * payout_status is its own field and the ledger can answer "what do we still
 * owe this shop?" without guessing.
 *
 * Shop approval gets a reason column so a rejected shop can be told why, and
 * approved_at so the queue can show how long an application waited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('payout_status', ['pending', 'paid'])->default('pending')->after('status')->index();
            $table->date('payout_date')->nullable()->after('payout_status');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->text('status_reason')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('status_reason');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->foreignId('status_changed_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payout_status', 'payout_date']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status_reason', 'approved_at']);
        });
    }
};
