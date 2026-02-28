<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('node_attestation_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('node_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('term_key');
            $table->string('signed_hash_artifact');
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['node_id', 'term_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_attestation_acceptances');
    }
};
