<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** Einmal hashen und wiederverwenden — Argon2 ist absichtlich langsam. */
    protected static ?string $password;

    /** Passwort aller Test-Benutzer, falls ein Test sich anmelden muss. */
    public const PASSWORD = 'richtig-langes-passwort';

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make(self::PASSWORD),
            'role' => Role::User,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function moderator(): static
    {
        return $this->state(['role' => Role::Moderator]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
