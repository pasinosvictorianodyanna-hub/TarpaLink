<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Webhook delivery attempts. After three the payment is marked failed
            // and surfaced on the admin dashboard instead of sitting in pending.
            $table->unsignedTinyInteger('attempts')->default(0)->after('status');
            $table->text('last_error')->nullable()->after('attempts');
            $table->timestamp('failed_at')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_error', 'failed_at']);
        });
    }
};
