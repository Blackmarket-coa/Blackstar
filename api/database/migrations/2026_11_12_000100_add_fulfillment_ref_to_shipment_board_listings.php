<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->string('source_fulfillment_ref')->nullable();
            // Existing order-only rows stay NULL. New parcel listings are
            // unique even if different inbound events race to create them.
            $table->unique(['source_order_ref', 'source_fulfillment_ref'], 'shipment_order_fulfillment_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->dropUnique('shipment_order_fulfillment_unique');
            $table->dropColumn('source_fulfillment_ref');
        });
    }
};
