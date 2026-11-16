<?php

// Creates the demo account used by screenshots.mjs on a LOCAL development site.
// Run: php artisan tinker docs/scripts/seed-demo.php
// The password is the UserFactory fixture value (App\Modules\Identity\Database\Factories\UserFactory::PASSWORD).

use App\Modules\Identity\Domain\User;

if (! app()->environment('local')) {
    throw new RuntimeException('seed-demo.php runs on a local development site only.');
}

User::query()->where('email', 'maya@example.com')->exists()
    || User::factory()->create(['name' => 'Maya Demo', 'email' => 'maya@example.com']);

echo "demo user: maya@example.com\n";
