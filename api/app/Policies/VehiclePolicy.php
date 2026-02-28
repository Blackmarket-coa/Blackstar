<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return !empty($user->node_id);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->node_id === $vehicle->node_id;
    }

    public function create(User $user): bool
    {
        return !empty($user->node_id);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->node_id === $vehicle->node_id;
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->node_id === $vehicle->node_id;
    }
}
