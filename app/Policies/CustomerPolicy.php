<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool { return $user->role === 'admin'; }
    public function view(User $user, Customer $model): bool { return $user->role === 'admin'; }
    public function create(User $user): bool { return $user->role === 'admin'; }
    public function update(User $user, Customer $model): bool { return $user->role === 'admin'; }
    public function delete(User $user, Customer $model): bool { return $user->role === 'admin'; }
    public function restore(User $user, Customer $model): bool { return $user->role === 'admin'; }
    public function forceDelete(User $user, Customer $model): bool { return $user->role === 'admin'; }
}
