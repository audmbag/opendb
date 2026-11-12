<?php

namespace Database\Factories;

use App\Enums\PrivilegeEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * The account that holds every privilege, like the root of MySQL.
     */
    public function root(): static
    {
        return $this->state(fn (array $attributes): array => ['is_root' => true]);
    }

    /**
     * A user holding the privileges on the whole instance, like `GRANT … ON *.*`.
     */
    public function withPrivileges(PrivilegeEnum ...$privileges): static
    {
        return $this->afterCreating(fn (User $user) => $user->grants()->createMany(
            array_map(fn (PrivilegeEnum $privilege): array => ['privilege' => $privilege], $privileges),
        ));
    }
}
