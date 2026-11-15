<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Domain\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local development data. The password is the factory's fixture value.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
