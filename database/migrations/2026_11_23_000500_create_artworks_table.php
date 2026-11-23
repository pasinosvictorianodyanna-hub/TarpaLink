<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The file the customer sends the shop to print.
 *
 * Separate from `proofs` because the two travel in opposite directions and mean
 * different things: an artwork is what the customer wants printed, a proof is
 * what the shop says it is about to print. The published privacy policy already
 * distinguishes them ("the artwork file you upload" versus "the proof the shop
 * uploads"), so the schema does too.
 *
 * Several rows per job on purpose. A customer who sends the wrong file, or a
 * revised one, has to be able to send another — and what the shop received, and
 * when, is worth keeping rather than overwriting. The newest row is the current
 * artwork; the older ones stay as the record of what was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            // Nullable so a file survives its uploader being removed; the job it
            // belongs to is the thing that matters, and `jobs` cascades anyway.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->timestamps();

            // The job page asks "what is the latest file on this job" on every
            // load, and the shop console asks the same question.
            $table->index(['job_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artworks');
    }
};
