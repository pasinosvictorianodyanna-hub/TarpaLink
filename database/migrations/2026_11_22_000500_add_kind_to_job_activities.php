<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The trail holds two different things, and it used to pretend they were one.
 *
 * A nudge is not a status change. Writing one as `from_status = to_status` made
 * the history read "Quoted → Quoted", which is not a thing that can happen and
 * quietly poisons any count of how often a job moved. `kind` says which sort of
 * row it is, and `to_status` becomes nullable so a note can carry no status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_activities', function (Blueprint $table) {
            $table->string('kind', 16)->default('transition')->after('user_id');
            $table->string('to_status', 24)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Notes have no status, so they cannot survive in the old shape. Delete
        // them rather than inventing a transition that never happened.
        DB::table('job_activities')->where('kind', 'note')->delete();

        Schema::table('job_activities', function (Blueprint $table) {
            $table->dropColumn('kind');
            $table->string('to_status', 24)->nullable(false)->change();
        });
    }
};
