<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Test and seed users. The default password is a fixture value, not a secret.
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    public const string PASSWORD = 'fixture passphrase 2026';

    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => app(Clock::class)->now(),
            'password_hash' => Hash::make(self::PASSWORD),
            'webauthn_user_handle' => random_bytes(32),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function withoutPassword(): static
    {
        return $this->state(fn (): array => ['password_hash' => null]);
    }
}
