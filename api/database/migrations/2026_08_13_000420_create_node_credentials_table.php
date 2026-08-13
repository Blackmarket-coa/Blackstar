<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('node_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Null node_id = a marketplace peer credential (e.g. FreeBlackMarket
            // itself); non-null = a partner node's machine credential.
            $table->foreignUuid('node_id')->nullable()->constrained('nodes')->nullOnDelete();
            $table->string('label');
            $table->string('key_id')->unique();
            // Encrypted at rest (Laravel encrypted cast), not hashed: HMAC
            // verification needs the raw secret back.
            $table->text('secret');
            $table->string('status')->default('active')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_credentials');
    }
};
