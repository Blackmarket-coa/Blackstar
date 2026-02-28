<?php

namespace Database\Factories;

use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NodeFactory extends Factory
{
    protected $model = Node::class;

    public function definition(): array
    {
        return [
            'node_id' => 'NODE-' . Str::upper(Str::random(8)),
            'legal_entity_name' => $this->faker->company(),
            'jurisdiction' => $this->faker->countryCode(),
            'service_radius' => $this->faker->randomFloat(2, 5, 200),
            'contact' => [
                'name' => $this->faker->name(),
                'email' => $this->faker->safeEmail(),
                'phone' => $this->faker->phoneNumber(),
            ],
            'insurance_attestation_hash' => hash('sha256', $this->faker->uuid()),
            'license_attestation_hash' => hash('sha256', $this->faker->uuid()),
            'transport_law_attestation_hash' => hash('sha256', $this->faker->uuid()),
            'platform_indemnification_attestation_hash' => hash('sha256', $this->faker->uuid()),
            'transport_capabilities' => ['van', 'bike'],
            'governance_room_id' => 'room-' . Str::lower(Str::random(10)),
            'reputation_score' => $this->faker->randomFloat(2, 0, 5),
            'is_active' => true,
            'activated_at' => now(),
        ];
    }
}
