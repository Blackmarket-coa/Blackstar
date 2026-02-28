<?php

namespace Database\Factories;

use App\Models\GovernanceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class GovernanceSettingFactory extends Factory
{
    protected $model = GovernanceSetting::class;

    public function definition(): array
    {
        return [
            'federation_council_room_id' => 'council-' . $this->faker->bothify('??####'),
        ];
    }
}
