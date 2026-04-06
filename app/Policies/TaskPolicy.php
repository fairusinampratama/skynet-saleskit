<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Task $model): bool { return $user->role === 'admin' || $model->assigned_to === $user->id; }
    public function create(User $user): bool { return $user->role === 'admin'; }
    public function update(User $user, Task $model): bool { return $user->role === 'admin' || $model->assigned_to === $user->id; }
    public function delete(User $user, Task $model): bool { return $user->role === 'admin'; }
    public function restore(User $user, Task $model): bool { return $user->role === 'admin'; }
    public function forceDelete(User $user, Task $model): bool { return $user->role === 'admin'; }
}
