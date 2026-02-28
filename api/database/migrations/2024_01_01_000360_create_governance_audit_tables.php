<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('governance_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('federation_council_room_id')->nullable();
            $table->timestamps();
        });

        Schema::create('governance_decision_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('node_id')->nullable()->constrained('nodes')->nullOnDelete();
            $table->foreignUuid('shipment_board_listing_id')->nullable()->constrained('shipment_board_listings')->nullOnDelete();
            $table->string('decision_ref')->index();
            $table->string('decision_type')->index();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->string('correlation_id')->nullable()->index();
            $table->foreignUuid('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['node_id', 'decision_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_decision_references');
        Schema::dropIfExists('governance_settings');
    }
};
