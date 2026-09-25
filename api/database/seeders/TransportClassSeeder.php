<?php

namespace Database\Seeders;

use App\Models\TransportClass;
use Illuminate\Database\Seeder;

/**
 * Canonical transport classes operators choose from.
 *
 * The set is the `(category, subtype)` pairs the code base already uses in
 * TransportClassFactory, ShipmentBoardListingFactory, the FBM
 * delivery-option fixture and the eligibility tests — no new modes are
 * introduced here. Limits are taken from those sources where they give one:
 *
 *   ground.van    TransportClassFactory defaults
 *   ground.bike   ShipmentBoardListingTest (weight 20, range 40)
 *   air.drone     ShipmentEligibilityServiceTest (weight 8, range 25)
 *   ground.truck  no source gives limits; the values below are placeholders
 *
 * Volume for bike/drone and every truck limit are likewise placeholders, and
 * no unit is fixed in the schema. An admin should review them before
 * relying on them for eligibility. All are `STD`, non-hazard: `(category,
 * subtype)` is unique, so a hazmat variant cannot share a row with its
 * standard counterpart.
 *
 * Idempotent and non-destructive: an existing row (matched on category +
 * subtype) is left as it is, so re-running never overwrites tuned limits.
 */
class TransportClassSeeder extends Seeder
{
    public const CLASSES = [
        [
            'category' => 'ground',
            'subtype' => 'van',
            'weight_limit' => 1000,
            'volume_limit' => 30,
            'range_limit' => 400,
            'hazard_capability' => false,
            'regulatory_class' => 'STD',
            'insurance_required_flag' => true,
        ],
        [
            'category' => 'ground',
            'subtype' => 'truck',
            'weight_limit' => 10000,
            'volume_limit' => 80,
            'range_limit' => 800,
            'hazard_capability' => false,
            'regulatory_class' => 'STD',
            'insurance_required_flag' => true,
        ],
        [
            'category' => 'ground',
            'subtype' => 'bike',
            'weight_limit' => 20,
            'volume_limit' => 1,
            'range_limit' => 40,
            'hazard_capability' => false,
            'regulatory_class' => 'STD',
            'insurance_required_flag' => false,
        ],
        [
            'category' => 'air',
            'subtype' => 'drone',
            'weight_limit' => 8,
            'volume_limit' => 1,
            'range_limit' => 25,
            'hazard_capability' => false,
            'regulatory_class' => 'STD',
            'insurance_required_flag' => false,
        ],
    ];

    public function run(): void
    {
        foreach (self::CLASSES as $class) {
            TransportClass::query()->firstOrCreate(
                ['category' => $class['category'], 'subtype' => $class['subtype']],
                $class
            );
        }
    }
}
