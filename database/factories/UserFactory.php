<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->userName(),
            'password' => Hash::make('password123'), // default login password
            'nama_lengkap' => $this->faker->name(),
            'role_id' => 1, // misalnya default ke Super Admin, sesuaikan dengan RoleSeeder
            'created_at' => now(),
        ];
    }
}
