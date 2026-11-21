<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every delivery the provider makes, including the ones that failed. This is
        // both the replay guard (event_id unique) and the evidence the admin
        // dashboard needs to show a payment that never settled.
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 24)->default('paymongo');
            $table->string('event_id', 64)->nullable()->unique();
            $table->string('intent_id', 64)->index();
            $table->string('type', 48);
            $table->json('payload');
            $table->enum('status', ['processed', 'ignored', 'failed'])->default('processed')->index();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
