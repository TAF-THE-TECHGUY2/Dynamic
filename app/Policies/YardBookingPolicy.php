<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\YardBooking;

/** The one thing the yard attendant can do. */
final class YardBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, YardBooking $booking): bool
    {
        return true;
    }
}
