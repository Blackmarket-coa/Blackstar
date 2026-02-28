<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('node_id')->unique();
            $table->string('legal_entity_name');
            $table->string('jurisdiction');
            $table->decimal('service_radius', 10, 2)->default(0);
            $table->json('contact')->nullable();
            $table->string('insurance_attestation_hash')->nullable();
            $table->string('license_attestation_hash')->nullable();
            $table->json('transport_capabilities')->nullable();
            $table->string('governance_room_id')->nullable();
            $table->decimal('reputation_score', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
