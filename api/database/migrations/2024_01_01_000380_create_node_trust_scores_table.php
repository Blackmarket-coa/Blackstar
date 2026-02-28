<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('node_trust_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('node_id')->unique()->constrained('nodes')->cascadeOnDelete();
            $table->decimal('on_time_rate', 6, 4)->default(0);
            $table->decimal('damage_rate', 6, 4)->default(0);
            $table->decimal('dispute_rate', 6, 4)->default(0);
            $table->decimal('governance_participation', 6, 4)->default(0);
            $table->decimal('on_time_component', 8, 4)->default(0);
            $table->decimal('damage_component', 8, 4)->default(0);
            $table->decimal('dispute_component', 8, 4)->default(0);
            $table->decimal('governance_component', 8, 4)->default(0);
            $table->decimal('aggregate_score', 8, 4)->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_trust_scores');
    }
};
