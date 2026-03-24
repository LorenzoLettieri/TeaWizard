<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = User::create([
            'name'=>'LolloLetti',
            'email'=>'lolloletti@email.com',
            'password'=> '12345678',
        ]);

        $admin->assignRole('admin');
    }
}
