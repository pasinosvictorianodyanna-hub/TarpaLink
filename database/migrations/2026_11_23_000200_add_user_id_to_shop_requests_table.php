<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request can now arrive from somebody who already has an account.
 *
 * Until now a request was anonymous: the administrator approved it, and only
 * then did an account come into existence, with a set-password link to hand
 * over. That meant mail delivery was load-bearing — with `MAIL_MAILER=log`, an
 * approved shop was one closed tab away from having no owner who could sign in.
 *
 * Now the applicant registers as they apply, so the account exists before the
 * decision does. Approval attaches the shop to that account and nothing else.
 *
 * Nullable because the older shape has to keep working: a request with no
 * `user_id` is one that still needs an account created and an invitation sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_requests', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
