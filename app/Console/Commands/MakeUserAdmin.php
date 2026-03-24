<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class MakeUserAdmin extends Command
{
    protected $signature = 'users:make-admin {email : Email of the user to promote}';

    protected $description = 'Promote an existing user to the admin role';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        foreach (Roles::all() as $role) {
            Role::findOrCreate($role, 'web');
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        if ($user->hasRole(Roles::ADMIN)) {
            $this->info("User [{$email}] is already an admin.");

            return self::SUCCESS;
        }

        $user->syncRoles([Roles::ADMIN]);

        $this->info("User [{$email}] is now an admin.");

        return self::SUCCESS;
    }
}
