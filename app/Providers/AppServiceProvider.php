<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin bypass semua pemeriksaan permission (Gate).
        Gate::before(function (?User $user, string $ability) {
            if (! $user) {
                return null;
            }

            if ($user->hasRole('superadmin')) {
                return true;
            }

            // RBAC belum ter-seed (migration belum dijalankan) -> jangan kunci user.
            static $rbacReady = null;

            if ($rbacReady === null) {
                $rbacReady = Role::where('name', 'superadmin')->exists();
            }

            return $rbacReady ? null : true;
        });
    }
}
