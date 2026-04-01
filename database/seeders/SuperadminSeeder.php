<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'superadmin@simevlap.go.id'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'opd_id' => null,
                'is_active' => true,
            ]
        );
        $user->assignRole('superadmin');
    }
}
