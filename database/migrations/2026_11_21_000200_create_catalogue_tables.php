<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('unit', 16)->default('sqft');
            $table->string('blurb')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['shop_id', 'slug']);
        });

        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('min_width', 8, 2)->default(0);
            $table->decimal('max_width', 8, 2)->nullable();
            $table->decimal('min_height', 8, 2)->default(0);
            $table->decimal('max_height', 8, 2)->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('price_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit', 24)->default('pc');
            $table->unsignedBigInteger('price');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('quantity_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_qty');
            $table->unsignedTinyInteger('percent');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quantity_discounts');
        Schema::dropIfExists('price_options');
        Schema::dropIfExists('price_tiers');
        Schema::dropIfExists('services');
    }
};
