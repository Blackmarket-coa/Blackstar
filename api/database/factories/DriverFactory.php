<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Fleet;
use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'node_id' => Node::factory(),
            'fleet_id' => Fleet::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
