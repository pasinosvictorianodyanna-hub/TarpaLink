<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trail has to hold a reply, which is longer and stranger than a note.
 *
 * `note` is a 255-character summary written by the shop, and it stays that: a
 * one-line heading. A customer's email needs somewhere to put the actual words,
 * hence `body`. `source` says the row arrived by mail rather than being typed
 * into the panel, so the history can be read without guessing who is speaking.
 *
 * `source_ref` is the dedupe key. Every inbound provider retries on a timeout,
 * and without it a single reply lands in the history two or three times, which
 * reads exactly like a customer who will not stop writing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_activities', function (Blueprint $table) {
            $table->string('source', 16)->nullable()->after('kind');
            $table->text('body')->nullable()->after('note');

            // Hashed message id, so the index never has to hold a 400-character
            // provider header and never carries a customer's address in it.
            $table->string('source_ref', 64)->nullable()->after('body');
            $table->unique('source_ref');
        });
    }

    public function down(): void
    {
        Schema::table('job_activities', function (Blueprint $table) {
            $table->dropUnique(['source_ref']);
            $table->dropColumn(['source', 'body', 'source_ref']);
        });
    }
};
