<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which nodes belong to which coalition, and which of them coordinates it.
 *
 * A pivot rather than a column on `nodes`, because a haulier can serve several
 * coalitions and picking one would force operators to choose. `coalition_ref`
 * is the opaque Blackout coalition id; there is no foreign key to chase because
 * the coalition lives in another system entirely.
 *
 * `role` exists for one reason: a coalition drive that arrives from FBM is
 * owned by the FBM service account, so nobody could award its bids. A
 * coordinator is the coalition's own answer to "who chooses the carrier".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_coalition_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->string('coalition_ref');
            $table->string('role')->default('member');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['node_id', 'coalition_ref']);
            $table->index(['coalition_ref', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_coalition_memberships');
    }
};
