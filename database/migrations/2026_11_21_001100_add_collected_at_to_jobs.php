<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the customer actually walked out with the job.
 *
 * `job_activities` records the status change, but it is a log, not a fact about
 * the job — and it is empty for anything seeded or imported. The shop dashboard
 * needs a column it can trust: on-time rate is collected jobs whose
 * `collected_at` is not later than `due_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->timestamp('collected_at')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn('collected_at');
        });
    }
};
