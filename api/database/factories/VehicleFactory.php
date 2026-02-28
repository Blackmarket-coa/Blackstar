<?php

namespace Database\Factories;

use App\Models\Fleet;
use App\Models\Node;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'node_id' => Node::factory(),
            'fleet_id' => Fleet::factory(),
            'name' => $this->faker->word() . ' Vehicle',
            'plate_number' => strtoupper($this->faker->bothify('??-####')),
        ];
    }
}
