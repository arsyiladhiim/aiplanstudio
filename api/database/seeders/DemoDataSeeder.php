<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use App\Models\Version;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $demoEmail = 'demo@aistack.dev';
        if (User::where('email', $demoEmail)->exists()) {
            $this->command?->info("Demo data already seeded. Skipping.");
            return;
        }

        $demo = User::create([
            'name' => 'Demo Admin',
            'email' => $demoEmail,
            'password' => Hash::make('demo1234'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $members = collect([
            ['name' => 'Budi Santoso', 'email' => 'budi@demo.dev', 'status' => 'active'],
            ['name' => 'Citra Dewi', 'email' => 'citra@demo.dev', 'status' => 'active'],
            ['name' => 'Dani Pratama', 'email' => 'dani@demo.dev', 'status' => 'pending'],
        ])->map(fn (array $u) => User::create([
            ...$u,
            'password' => Hash::make('demo1234'),
            'role' => 'member',
        ]));

        $sampleProjects = [
            ['Kasir UMKM Mobile', 'Aplikasi kasir untuk warung dengan stok & laporan harian.', 'both'],
            ['SaaS Project Manager', 'Dashboard tim untuk task, timeline, dan billing.', 'web'],
            ['Marketplace Jasa Lokal', 'Platform mempertemukan penyedia jasa & pelanggan sekitar.', 'both'],
            ['Habit Tracker', 'Pelacak kebiasaan dengan streak & pengingat.', 'both'],
            ['Internal CRM', 'Manajemen kontak & deal untuk sales internal.', 'web'],
        ];

        $allStages = Version::ALL_STAGES;
        $idx = 0;
        foreach ($sampleProjects as [$title, $idea, $target]) {
            $project = Project::create([
                'user_id' => $idx % 2 === 0 ? $demo->id : $members->first()->id,
                'title' => $title,
                'idea' => $idea,
                'target' => $target,
                'stack' => $target === 'web' ? 'Next.js + Laravel + Postgres' : 'Next.js + Laravel + Flutter + Postgres',
            ]);

            $completedStages = match ($idx) {
                0 => array_slice($allStages, 0, 4),
                1 => array_slice($allStages, 0, 9),
                2 => array_slice($allStages, 0, 2),
                3 => $allStages,
                default => array_slice($allStages, 0, 6),
            };

            $status = [];
            foreach ($allStages as $stage) {
                $status[$stage] = in_array($stage, $completedStages) ? 'done' : 'pending';
            }

            Version::create([
                'project_id' => $project->id,
                'version_no' => 1,
                'stage_status' => $status,
            ]);

            $idx++;
        }

        Template::create([
            'user_id' => $demo->id,
            'name' => 'Demo SaaS Starter',
            'target' => 'web',
            'description' => 'Auth + billing + dashboard analytics siap pakai.',
            'seed' => [
                'title' => 'SaaS Starter',
                'idea' => 'Aplikasi SaaS dengan autentikasi, billing, dan dashboard analitik.',
                'target' => 'web',
            ],
        ]);

        Template::create([
            'user_id' => $demo->id,
            'name' => 'Demo Mobile CRUD',
            'target' => 'both',
            'description' => 'CRUD app dengan sync offline dan push notification.',
            'seed' => [
                'title' => 'Mobile CRUD App',
                'idea' => 'Aplikasi CRUD mobile dengan sinkronisasi offline.',
                'target' => 'both',
            ],
        ]);

        $this->command?->info("Demo data seeded. Login: demo@aistack.dev / demo1234");
    }
}
