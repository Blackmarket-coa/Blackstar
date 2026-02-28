<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return !empty($user->node_id);
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->node_id === $driver->node_id;
    }

    public function create(User $user): bool
    {
        return !empty($user->node_id);
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->node_id === $driver->node_id;
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $user->node_id === $driver->node_id;
    }
}
