<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One ledger row per captured payment. payment_id is unique, so a replayed
        // webhook cannot credit the same job twice — the constraint does the work,
        // not a "check first" in PHP that two concurrent requests could both pass.
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();

            // All centavos. gross - commission = net, enforced by construction.
            $table->unsignedBigInteger('gross');
            $table->unsignedBigInteger('commission');
            $table->unsignedBigInteger('net');

            // The rate that was actually applied, in basis points. Snapshotted so a
            // later plan change cannot rewrite history.
            $table->unsignedSmallInteger('commission_rate');

            $table->enum('status', ['pending', 'scheduled', 'paid'])->default('pending')->index();
            $table->date('payout_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
