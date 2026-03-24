<?php

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (Roles::all() as $role) {
            Role::findOrCreate($role, 'web');
        }

        User::query()
            ->whereDoesntHave('roles')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $user->syncRoles([Roles::USER]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', Roles::all())
            ->get();

        foreach ($roles as $role) {
            $role->delete();
        }
    }
};
