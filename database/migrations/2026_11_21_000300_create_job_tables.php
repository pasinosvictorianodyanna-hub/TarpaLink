<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->enum('status', [
                'quoted', 'awaiting_proof', 'approved', 'in_production', 'ready', 'collected', 'cancelled',
            ])->default('quoted')->index();
            $table->json('spec');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedInteger('matrix_version')->default(1);
            $table->string('pickup_city')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('job_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('qty', 10, 2)->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamps();
        });

        Schema::create('proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('file_path');
            $table->enum('status', ['sent', 'approved', 'rejected'])->default('sent');
            $table->text('note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['job_id', 'version']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 24)->default('paymongo');
            $table->string('intent_id')->unique();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('processing_fee')->default(0);
            $table->unsignedBigInteger('commission')->default(0);
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('proofs');
        Schema::dropIfExists('job_lines');
        Schema::dropIfExists('jobs');
    }
};
