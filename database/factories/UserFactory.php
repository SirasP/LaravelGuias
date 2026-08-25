<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'viewer',
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'viewer',
        ]);
    }

    public function bodeguero(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'bodeguero',
        ]);
    }

    /** Revisor de Compras o Jefatura. */
    public function comprador(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => \App\Models\User::ROLE_BUYER,
        ]);
    }

    /** Consulta de sólo lectura. */
    public function auditor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => \App\Models\User::ROLE_AUDITOR,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
