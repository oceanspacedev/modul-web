<?php

namespace Database\Factories;

use App\Models\Role;
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
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->userName(),
            'nama_lengkap' => strtoupper($this->faker->name()),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'divisi_id' => 1,
        ];
    }

    /**
     * Pasang satu jabatan (role) setelah user dibuat.
     */
    public function jabatan(string $name): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignJabatan(Role::findByName($name, 'web'), dataAccessChosen: true));
    }

    /**
     * Super admin: jabatan ADMIN dengan akses seluruh badan usaha.
     */
    public function admin(): static
    {
        return $this->state(['data_access' => User::DATA_ACCESS_ALL])->jabatan(User::ROLE_ADMIN);
    }

    public function staff(): static
    {
        return $this->jabatan(User::ROLE_STAFF);
    }

    /**
     * Hak akses tambahan trainer di luar jabatan.
     */
    public function trainer(): static
    {
        return $this->afterCreating(fn (User $user) => $user->setTrainer(true));
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }
}
