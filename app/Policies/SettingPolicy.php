<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Settings carry the banking details printed on every invoice. Owner only.
 */
final class SettingPolicy
{
    public function view(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user): bool
    {
        return $user->isOwner();
    }
}
