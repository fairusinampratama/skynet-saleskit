<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_baseline_users_and_areas_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('username', 'admin')->where('role', 'admin')->count());
        $this->assertSame(1, User::query()->where('username', 'tech')->where('role', 'technician')->count());
        $this->assertSame(3, Area::query()->where('active', true)->count());
        $this->assertTrue(Area::query()->where('code', 'MLG-01')->where('name', 'Malang Kota')->exists());
    }
}
