<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin SalesKit',
            'username' => 'admin',
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => 'Technician SalesKit',
            'username' => 'tech',
            'role' => 'technician',
            'email' => 'tech@example.com',
        ]);
    }
}
