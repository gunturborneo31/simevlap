<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['superadmin', 'admin', 'pimpinan', 'verifikator', 'opd'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->migrateLegacyRole('user', 'opd');
        $this->migrateLegacyRole('evaluator', 'verifikator');
    }

    private function migrateLegacyRole(string $legacyRole, string $newRole): void
    {
        $roleModel = Role::query()->where([
            'name' => $legacyRole,
            'guard_name' => 'web',
        ])->first();
        if (!$roleModel) {
            return;
        }

        User::role($legacyRole)->get()->each(function (User $user) use ($legacyRole, $newRole): void {
            $user->removeRole($legacyRole);
            if (!$user->hasRole($newRole)) {
                $user->assignRole($newRole);
            }
        });

        $roleModel->delete();
    }
}
