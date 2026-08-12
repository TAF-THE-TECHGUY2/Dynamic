<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the single owner account from env. There is no public registration.
 */
class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('business.owner.email');
        $password = config('business.owner.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException(
                'Set OWNER_EMAIL and OWNER_PASSWORD in .env before seeding the owner account.'
            );
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        $user->name = config('business.owner.name');
        $user->role = UserRole::Owner;
        $user->email_verified_at ??= now();

        // Only ever set the password when creating the account. Re-running the
        // seeder must not reset a password the owner has since changed.
        if (! $user->exists) {
            $user->password = $password;
        }

        $user->save();

        $this->command->info("Owner account ready: {$email}");
    }
}
