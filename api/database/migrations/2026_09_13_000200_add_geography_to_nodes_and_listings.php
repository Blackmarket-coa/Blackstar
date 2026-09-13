<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give a node a location, and a listing an origin.
 *
 * `nodes.service_radius` has existed since the table was created. It is
 * required on node creation, validated as numeric, stored — and read by
 * nothing, because there was no coordinate to measure it from. A radius
 * without a centre cannot mean anything, so every operator who filled that
 * field in was answering a question the system then discarded.
 *
 * What geography there was: `ShipmentEligibilityService` compared
 * `node.jurisdiction` to `listing.jurisdiction` as exact strings. A node in
 * California was therefore eligible for a listing in New York whenever both
 * said "US", and the board offered it to them.
 *
 * These columns are what `service_radius` needs to become real. All four are
 * nullable, deliberately: existing nodes and listings have no coordinates and
 * must keep working, so the distance check applies only where both ends are
 * known and falls back to jurisdiction matching otherwise. Populating them is
 * a backfill, not a precondition for deploying this.
 *
 * `service_radius` is interpreted as **miles**. The column has carried no unit
 * since it was created; miles matches the US focus of the deployment and the
 * `service_area_radius_miles` field the FBM side already names explicitly. The
 * interpretation lives in `App\Support\Geo` so there is one place to change it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            // 7 decimal places ≈ 1cm, far finer than needed and the usual
            // choice; 6 would round a service centre by up to ~10cm.
            $table->decimal('latitude', 10, 7)->nullable()->after('jurisdiction');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->decimal('origin_latitude', 10, 7)->nullable()->after('origin');
            $table->decimal('origin_longitude', 10, 7)->nullable()->after('origin_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('shipment_board_listings', function (Blueprint $table) {
            $table->dropColumn(['origin_latitude', 'origin_longitude']);
        });
    }
};
