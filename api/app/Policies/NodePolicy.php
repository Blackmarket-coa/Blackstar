<?php

namespace App\Policies;

use App\Models\Node;
use App\Models\User;

class NodePolicy
{
    public function viewAny(User $user): bool
    {
        return !empty($user->node_id);
    }

    public function view(User $user, Node $node): bool
    {
        return $user->node_id === $node->id;
    }

    public function create(User $user): bool
    {
        return !empty($user->node_id);
    }

    public function update(User $user, Node $node): bool
    {
        return $user->node_id === $node->id;
    }

    public function delete(User $user, Node $node): bool
    {
        return $user->node_id === $node->id;
    }
}
