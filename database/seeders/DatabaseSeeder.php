<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin SalesKit',
                'role' => 'admin',
                'email' => 'admin@skynet.com',
                'password' => Hash::make((string) env('SEED_ADMIN_PASSWORD', 'password')),
            ],
        );

        User::updateOrCreate(
            ['username' => 'tech'],
            [
                'name' => 'Teknisi SalesKit',
                'role' => 'technician',
                'email' => 'tech@skynet.com',
                'password' => Hash::make((string) env('SEED_TECHNICIAN_PASSWORD', 'password')),
            ],
        );

        foreach ([
            ['code' => 'MLG-01', 'name' => 'Malang Kota'],
            ['code' => 'MLG-02', 'name' => 'Malang Selatan'],
            ['code' => 'MLG-03', 'name' => 'Malang Utara'],
        ] as $area) {
            Area::updateOrCreate(
                ['code' => $area['code']],
                [
                    'name' => $area['name'],
                    'active' => true,
                ],
            );
        }
    }
}
