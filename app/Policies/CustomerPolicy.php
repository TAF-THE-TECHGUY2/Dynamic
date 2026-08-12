<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->seesSales();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->role->seesSales();
    }

    public function create(User $user): bool
    {
        return $user->role->seesSales();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->role->seesSales();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isOwner();
    }
}
