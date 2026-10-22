<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * Middleware role yang kompatibel dengan cache route lama.
 *
 * Aplikasi ini pernah memakai `role:Admin`. Kalau route cache di server masih
 * menyimpan middleware lama itu, user dengan role `superadmin` tetap harus
 * bisa masuk. Karena itu:
 *  - `Admin` (lama) dianggap sama dengan `superadmin`
 *  - `superadmin` selalu boleh
 *  - kalau tabel role belum ter-seed (migration belum dijalankan), tidak ada
 *    user yang dikunci (biar aplikasi tidak terkunci total).
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Alias role lama -> role baru.
        $normalized = array_map(
            fn (string $role) => strcasecmp($role, 'Admin') === 0 ? 'superadmin' : $role,
            $roles
        );

        if ($user->hasRole('superadmin')) {
            return $next($request);
        }

        if ($user->hasAnyRole($normalized)) {
            return $next($request);
        }

        // RBAC belum di-seed -> jangan kunci user.
        if (! $this->rbacReady()) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki hak akses untuk bagian ini.');
    }

    private function rbacReady(): bool
    {
        static $ready = null;

        if ($ready === null) {
            $ready = Role::where('name', 'superadmin')->exists();
        }

        return (bool) $ready;
    }
}
