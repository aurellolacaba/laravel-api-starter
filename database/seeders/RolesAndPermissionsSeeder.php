<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's roles and permissions.
     */
    /**
     * The guard the API authenticates against. All requests go through the JWT
     * `api` guard, and the `auth:api` middleware makes it the default guard at
     * runtime, so roles and permissions must be created under this same guard
     * for authorization checks to resolve them.
     */
    private const GUARD = 'api';

    public function run(): void
    {
        // Reset the cached roles and permissions so newly created records are picked up.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, self::GUARD);
        }

        // Admin gets every permission.
        $admin = Role::findOrCreate('admin', self::GUARD);
        $admin->syncPermissions(Permission::where('guard_name', self::GUARD)->get());

        // Manager can view and create users but not update or delete them.
        $manager = Role::findOrCreate('manager', self::GUARD);
        $manager->syncPermissions(['users.view', 'users.create']);

        // Plain user has no management permissions.
        Role::findOrCreate('user', self::GUARD);

        // Seed a default admin account so the protected endpoints can be exercised.
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $adminUser->assignRole($admin);
    }
}
