<?php

namespace App\Policies;

use App\Models\Fleet;
use App\Models\User;

class FleetPolicy
{
    public function viewAny(User $user): bool
    {
        return !empty($user->node_id);
    }

    public function view(User $user, Fleet $fleet): bool
    {
        return $user->node_id === $fleet->node_id;
    }

    public function create(User $user): bool
    {
        return !empty($user->node_id);
    }

    public function update(User $user, Fleet $fleet): bool
    {
        return $user->node_id === $fleet->node_id;
    }

    public function delete(User $user, Fleet $fleet): bool
    {
        return $user->node_id === $fleet->node_id;
    }
}
