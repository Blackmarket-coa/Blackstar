<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->string('required_category')->nullable()->after('jurisdiction');
            $table->string('required_subtype')->nullable()->after('required_category');
            $table->decimal('required_weight_limit', 12, 2)->nullable()->after('required_subtype');
            $table->decimal('required_range_limit', 12, 2)->nullable()->after('required_weight_limit');
            $table->boolean('requires_hazard_capability')->default(false)->after('required_range_limit');
            $table->string('required_regulatory_class')->nullable()->after('requires_hazard_capability');
            $table->boolean('insurance_required_flag')->default(false)->after('required_regulatory_class');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->dropColumn([
                'required_category',
                'required_subtype',
                'required_weight_limit',
                'required_range_limit',
                'requires_hazard_capability',
                'required_regulatory_class',
                'insurance_required_flag',
            ]);
        });
    }
};
