<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'node_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('node_id')->nullable()->after('id')->constrained('nodes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'node_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('node_id');
        });
    }
};
