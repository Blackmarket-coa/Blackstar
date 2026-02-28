<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipment_legs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipment_board_listing_id')->constrained('shipment_board_listings')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->foreignUuid('from_node_id')->nullable()->constrained('nodes')->nullOnDelete();
            $table->foreignUuid('to_node_id')->nullable()->constrained('nodes')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('proof_of_handoff_hash')->nullable();
            $table->string('settlement_ref')->nullable();
            $table->timestamps();

            $table->unique(['shipment_board_listing_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_legs');
    }
};
