<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->string('job_type')->default('delivery')->after('claim_policy');
            $table->decimal('bounty_amount', 12, 2)->nullable()->after('job_type');
            $table->string('bounty_currency', 8)->nullable()->after('bounty_amount');
            $table->text('work_order')->nullable()->after('destination');
            $table->json('creator_qa_checklist')->nullable()->after('work_order');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->dropColumn([
                'job_type',
                'bounty_amount',
                'bounty_currency',
                'work_order',
                'creator_qa_checklist',
            ]);
        });
    }
};
