<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipment_board_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_order_ref')->index();
            $table->string('status')->default('open')->index();
            $table->string('claim_policy')->default('first_claim');
            $table->string('jurisdiction')->nullable()->index();
            $table->json('required_transport_capabilities')->nullable();
            $table->foreignUuid('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('claimed_by_node_id')->nullable()->constrained('nodes')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_board_listings');
    }
};
