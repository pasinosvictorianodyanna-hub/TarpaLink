<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city')->index();
            $table->string('address');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('phone')->nullable();
            $table->text('description')->nullable();
            $table->string('accent', 24)->default('ink');
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending')->index();
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('jobs_done')->default(0);
            $table->boolean('accepts_rush')->default(true);
            $table->timestamps();
        });

        Schema::create('shop_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'staff'])->default('staff');
            $table->timestamps();
            $table->unique(['shop_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_users');
        Schema::dropIfExists('shops');
    }
};
