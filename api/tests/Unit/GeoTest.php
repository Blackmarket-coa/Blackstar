<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_known_distances_are_right(): void
    {
        // San Francisco to New York is about 2,570 miles.
        $this->assertEqualsWithDelta(
            2570,
            Geo::distanceMiles(37.7749, -122.4194, 40.7128, -74.0060),
            25
        );

        // Los Angeles to San Francisco is about 347.
        $this->assertEqualsWithDelta(
            347,
            Geo::distanceMiles(34.0522, -118.2437, 37.7749, -122.4194),
            5
        );
    }

    public function test_a_point_is_zero_miles_from_itself(): void
    {
        $this->assertSame(0.0, Geo::distanceMiles(37.7749, -122.4194, 37.7749, -122.4194));
    }

    public function test_unknown_coordinates_are_null_not_zero(): void
    {
        // "We do not know where this is" and "this is zero miles away" are
        // opposite answers. A caller that cannot tell them apart treats every
        // node with no coordinates as sitting on top of every listing.
        $this->assertNull(Geo::distanceMiles(null, -122.4194, 40.7128, -74.0060));
        $this->assertNull(Geo::distanceMiles(37.7749, null, 40.7128, -74.0060));
        $this->assertNull(Geo::distanceMiles(37.7749, -122.4194, null, -74.0060));
        $this->assertNull(Geo::distanceMiles(37.7749, -122.4194, 40.7128, null));
    }

    public function test_accepts_the_strings_a_decimal_cast_returns(): void
    {
        // Eloquent's `decimal:7` cast hands back strings, not floats.
        $this->assertEqualsWithDelta(
            347,
            Geo::distanceMiles('34.0522000', '-118.2437000', '37.7749000', '-122.4194000'),
            5
        );
    }

    public function test_is_symmetric(): void
    {
        $there = Geo::distanceMiles(51.5074, -0.1278, 48.8566, 2.3522);
        $back = Geo::distanceMiles(48.8566, 2.3522, 51.5074, -0.1278);
        $this->assertEqualsWithDelta($there, $back, 0.0001);
    }
}
