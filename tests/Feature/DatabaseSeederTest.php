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

    public function test_database_seeder_reuses_existing_user_with_seed_email(): void
    {
        User::factory()->create([
            'username' => 'teknisi',
            'email' => 'tech@skynet.com',
            'role' => 'technician',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'tech@skynet.com')->count());
        $this->assertTrue(User::query()->where('username', 'tech')->where('email', 'tech@skynet.com')->exists());
    }
}
