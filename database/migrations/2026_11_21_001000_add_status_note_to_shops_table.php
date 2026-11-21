<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Why a shop is not active. Set on rejection, cleared on approval, and
            // shown to the owner so "you cannot log in" is never unexplained.
            $table->string('status_note')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('status_note');
        });
    }
};
