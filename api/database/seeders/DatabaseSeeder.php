<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin dari env
        $adminEmail = env('SEED_ADMIN_EMAIL', 'admin@aistack.dev');
        $adminPassword = env('SEED_ADMIN_PASSWORD', 'password123');

        User::create([
            'name' => 'Admin',
            'email' => $adminEmail,
            'password' => Hash::make($adminPassword),
            'role' => 'admin',
        ]);

        // AI Provider kosong (diisi admin lewat UI)
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);

        // Templates minimal
        Template::insert([
            [
                'name' => 'SaaS Dashboard',
                'target' => 'web',
                'description' => 'Auth, billing, multi-tenant, dashboard analytics.',
                'seed' => json_encode(['idea' => 'SaaS dengan autentikasi multi-user dan billing.']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'E-Commerce',
                'target' => 'both',
                'description' => 'Katalog, keranjang, checkout, pembayaran.',
                'seed' => json_encode(['idea' => 'Marketplace dengan katalog produk dan checkout.']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mobile CRUD',
                'target' => 'mobile',
                'description' => 'App data sederhana dengan sync offline.',
                'seed' => json_encode(['idea' => 'Aplikasi mobile untuk mengelola data inventory offline-first.']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
