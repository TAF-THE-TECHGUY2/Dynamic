<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Publishing a price changes what every new quote is worth and what the
 * homepage tells the public. That is the owner's decision alone.
 */
final class PricePolicy
{
    public function view(User $user): bool
    {
        return $user->role->seesSales();
    }

    public function publish(User $user): bool
    {
        return $user->isOwner();
    }
}
