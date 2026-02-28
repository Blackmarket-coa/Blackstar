<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipment_bids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipment_board_listing_id')->constrained('shipment_board_listings')->cascadeOnDelete();
            $table->foreignUuid('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('USD');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['shipment_board_listing_id', 'node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_bids');
    }
};
