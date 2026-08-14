<?php

namespace Tests\Feature;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_demo_admin_and_members(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'demo@aistack.dev', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'budi@demo.dev', 'role' => 'member', 'status' => 'active']);
        $this->assertDatabaseHas('users', ['email' => 'dani@demo.dev', 'role' => 'member', 'status' => 'pending']);
    }

    public function test_seeds_five_projects(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(5, \App\Models\Project::count());
        $this->assertSame(5, \App\Models\Version::count());
    }

    public function test_seeds_two_user_templates(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(2, \App\Models\Template::whereNotNull('user_id')->count());
    }

    public function test_is_idempotent(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(1, \App\Models\User::where('email', 'demo@aistack.dev')->count());
        $this->assertSame(5, \App\Models\Project::count());
        $this->assertSame(2, \App\Models\Template::whereNotNull('user_id')->count());
    }
}
