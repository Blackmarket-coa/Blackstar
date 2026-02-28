<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fbm_inbound_event_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_id')->unique();
            $table->string('event_type')->index();
            $table->string('correlation_id')->nullable()->index();
            $table->json('payload');
            $table->string('status')->default('processing')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fbm_outbound_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type')->index();
            $table->string('correlation_id')->nullable()->index();
            $table->json('payload');
            $table->string('signature', 255)->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fbm_outbound_events');
        Schema::dropIfExists('fbm_inbound_event_receipts');
    }
};
