<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            // `shops.plan` already holds this code, so the relation joins on it
            // instead of forcing a backfill of a new foreign key.
            $table->string('code', 24)->unique();
            $table->string('name');
            $table->unsignedBigInteger('price')->default(0);          // per month, centavos
            $table->unsignedSmallInteger('commission_rate')->default(500); // basis points
            $table->string('blurb')->nullable();
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->enum('status', ['active', 'cancelled', 'past_due'])->default('active')->index();
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 32)->unique();
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['open', 'paid', 'void'])->default('open')->index();
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
