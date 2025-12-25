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
            // 'role_id' DIHAPUS karena sudah pindah ke tabel user_roles
            'is_active' => true, // tambahkan ini jika ada kolom status
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * State khusus untuk memberikan role secara otomatis saat testing
     */
    public function withRole($roleName): static
    {
        return $this->afterCreating(function (\App\Models\User $user) use ($roleName) {
            $role = \App\Models\Role::where('role_name', $roleName)->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }
        });
    }
}