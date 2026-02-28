<?php

namespace Database\Factories;

use App\Models\TransportClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransportClassFactory extends Factory
{
    protected $model = TransportClass::class;

    public function definition(): array
    {
        return [
            'category' => 'ground',
            'subtype' => 'van',
            'weight_limit' => 1000,
            'range_limit' => 400,
            'hazard_capability' => false,
            'regulatory_class' => 'STD',
            'insurance_required_flag' => true,
        ];
    }
}
