<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function (User $user, $ability) {
            if ($user->hasRole('Admin')) {
                return true;
            }
        });

        Gate::define('role', function (User $user, $role) {
            if (is_array($role)) {
                return $user->hasAnyRole($role);
            }

            if (is_string($role) && str_starts_with($role, 'all:')) {
                $list = substr($role, 4);
                return $user->hasAllRoles($list);
            }

            if (is_string($role) && str_contains($role, ',')) {
                $roles = array_map('trim', explode(',', $role));
                return $user->hasAnyRole($roles);
            }

            return $user->hasRole((string) $role);
        });

        Gate::define('roles', function (User $user, $roles) {
            if (is_string($roles) && str_starts_with($roles, 'all:')) {
                $list = substr($roles, 4);
                return $user->hasAllRoles($list);
            }

            if (is_string($roles)) {
                $roles = array_map('trim', explode(',', $roles));
            }

            return $user->hasAnyRole((array) $roles);
        });

        Gate::define('manage-users', fn(User $user) => $user->hasRole('Admin'));
        Gate::define('manage-classes', fn(User $user) => $user->hasAnyRole(['Admin', 'Guru']));
    }
}
