<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->string('origin')->nullable()->after('source_order_ref');
            $table->string('destination')->nullable()->after('origin');
            $table->decimal('required_volume_limit', 12, 2)->nullable()->after('required_weight_limit');
            $table->foreignUuid('current_node_id')->nullable()->after('claimed_by_node_id')->constrained('nodes')->nullOnDelete();
        });

        DB::table('shipment_board_listings')
            ->whereNull('current_node_id')
            ->whereNotNull('claimed_by_node_id')
            ->update(['current_node_id' => DB::raw('claimed_by_node_id')]);
    }

    public function down(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_node_id');
            $table->dropColumn(['origin', 'destination', 'required_volume_limit']);
        });
    }
};
