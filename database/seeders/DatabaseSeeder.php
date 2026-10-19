<?php

declare(strict_types=1);

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
        // Administrador de demonstração: SOMENTE ambiente local/testes. A senha vem do .env.
        if (app()->environment('production')) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@oficina.local'],
            [
                'name' => 'Administrador',
                'password' => (string) config('app.admin_seed_password'),
            ],
        );
    }
}
