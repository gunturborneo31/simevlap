<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OpdSeeder::class,
            LegacyUsersSeeder::class,
            UrusanBidangUrusanSeeder::class,
            SuperadminSeeder::class,
            KepmenSeeder::class,
            IkuSeeder::class,
            // ProgramUnggulanSeeder::class,
            ProgramUnggulanToAksiSeeder::class,
            DataDasarSeeder::class,
            KegiatanApbdSeeder::class,
            SubKegiatanApbdSeeder::class,
            IndikatorDpaSeeder::class,
            RenjaRkpdSeeder::class,
        ]);
    }
}
