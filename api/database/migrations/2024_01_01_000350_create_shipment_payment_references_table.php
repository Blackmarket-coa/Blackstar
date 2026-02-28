<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipment_payment_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipment_board_listing_id')->unique()->constrained('shipment_board_listings')->cascadeOnDelete();
            $table->string('buyer_vendor_payment_ref')->nullable()->index();
            $table->string('vendor_node_settlement_ref')->nullable()->index();
            $table->string('platform_fee_ref')->nullable()->index();
            $table->string('correlation_id')->nullable()->index();
            $table->foreignUuid('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_payment_references');
    }
};
