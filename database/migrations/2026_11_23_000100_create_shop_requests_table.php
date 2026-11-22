<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shop asking to be let in.
 *
 * Deliberately its own table rather than a `shops` row with `status = pending`.
 * A request is not a shop: there is no owner account yet, no price matrix, no
 * storefront, and nothing for the approval queue to approve. Creating a
 * half-shop for every enquiry would put unowned rows in the shop list, in the
 * pending count on the admin dashboard, and in every query that assumes a shop
 * has somebody who runs it.
 *
 * `shop_id` is the link back to what the request became, so the queue can say
 * "approved, became Inkwell Press" instead of losing the thread.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_requests', function (Blueprint $table) {
            $table->id();

            $table->string('business_name');
            $table->string('contact_name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('city');
            $table->string('address');

            // What they want to sell, in their own words. The price matrix is
            // built after approval, so this is a list of services, not prices.
            $table->text('services')->nullable();
            $table->text('message')->nullable();

            $table->enum('status', ['new', 'approved', 'rejected'])->default('new')->index();
            $table->text('reason')->nullable();

            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_requests');
    }
};
