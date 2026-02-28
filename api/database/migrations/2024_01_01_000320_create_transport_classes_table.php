<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transport_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category');
            $table->string('subtype');
            $table->decimal('weight_limit', 12, 2)->nullable();
            $table->decimal('range_limit', 12, 2)->nullable();
            $table->boolean('hazard_capability')->default(false);
            $table->string('regulatory_class')->nullable();
            $table->boolean('insurance_required_flag')->default(false);
            $table->timestamps();

            $table->unique(['category', 'subtype']);
        });

        Schema::create('node_transport_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignUuid('transport_class_id')->constrained('transport_classes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['node_id', 'transport_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_transport_classes');
        Schema::dropIfExists('transport_classes');
    }
};
