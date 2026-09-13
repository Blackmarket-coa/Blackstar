<?php

namespace App\Support;

/**
 * Distance between two points on the earth.
 *
 * Exists because `nodes.service_radius` had no way to be evaluated: there was
 * no coordinate on either a node or a listing, so the column was collected from
 * operators and never read. See the migration that added the coordinates.
 */
class Geo
{
    /**
     * Mean earth radius in miles.
     *
     * `service_radius` carries no unit in the schema and never has. It is
     * interpreted as miles, here and nowhere else, so the interpretation has
     * exactly one home if it ever needs to change.
     */
    public const EARTH_RADIUS_MILES = 3958.7613;

    /**
     * Great-circle distance in miles, or null when either point is unknown.
     *
     * Null rather than a sentinel distance: "we do not know where this is" and
     * "this is zero miles away" are opposite answers, and a caller that cannot
     * tell them apart will treat every node with no coordinates as sitting on
     * top of every listing.
     */
    public static function distanceMiles(
        int|float|string|null $lat1,
        int|float|string|null $lon1,
        int|float|string|null $lat2,
        int|float|string|null $lon2
    ): ?float {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }

        // Decimal casts hand these back as strings.
        $lat1 = (float) $lat1;
        $lon1 = (float) $lon1;
        $lat2 = (float) $lat2;
        $lon2 = (float) $lon2;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * self::EARTH_RADIUS_MILES * asin(min(1.0, sqrt($a)));
    }
}
