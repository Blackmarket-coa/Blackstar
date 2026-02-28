<?php

namespace Database\Factories;

use App\Models\Fleet;
use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;

class FleetFactory extends Factory
{
    protected $model = Fleet::class;

    public function definition(): array
    {
        return [
            'node_id' => Node::factory(),
            'name' => $this->faker->company() . ' Fleet',
            'status' => 'active',
        ];
    }
}
