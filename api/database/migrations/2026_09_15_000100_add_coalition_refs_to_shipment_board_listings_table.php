<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carry the coalition and drive a listing came from.
 *
 * A coalition goods drive that needs physical fulfilment arrives on the same
 * shipment board as every other job — no parallel board, no second claim
 * mechanic. These two refs are what let the board offer a coalition's freight
 * to that coalition's own nodes first, and what lets the drive be found again
 * when the goods move.
 *
 * Both are opaque Blackout ids. Blackstar never parses them; it only compares
 * them, so the id spaces stay independent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->string('coalition_ref')->nullable()->after('source_order_ref');
            $table->string('drive_ref')->nullable()->after('coalition_ref');
            $table->index('coalition_ref');
            $table->index('drive_ref');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->dropIndex(['drive_ref']);
            $table->dropIndex(['coalition_ref']);
            $table->dropColumn(['drive_ref', 'coalition_ref']);
        });
    }
};
