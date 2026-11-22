<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The SRS has a Shop staff actor, but `users.role` only admitted three
     * values. Staff are a real account type now: they work the queue and the
     * board, while the owner keeps prices and billing.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['customer', 'shop_owner', 'staff', 'admin'])
                ->default('customer')
                ->change();
        });
    }

    public function down(): void
    {
        // Refuse to trap existing staff rows in an invalid enum.
        if (DB::table('users')->where('role', 'staff')->exists()) {
            throw new \RuntimeException('Remove staff accounts before rolling back this migration.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['customer', 'shop_owner', 'admin'])
                ->default('customer')
                ->change();
        });
    }
};
