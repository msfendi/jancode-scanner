<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * Akses modul (halaman + menu).
 *
 * User boleh membuka modul bila punya permission `<modul>.view` ATAU
 * `<modul>.manage`. Aksi tulis (tambah/hapus/finish/scan) tetap memakai
 * `<modul>.manage`.
 */
class ModuleAccess
{
    public function handle(Request $request, Closure $next, string $module)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasRole('superadmin')) {
            return $next($request);
        }

        if ($user->can("{$module}.view") || $user->can("{$module}.manage")) {
            return $next($request);
        }

        // RBAC belum ter-seed -> jangan kunci user.
        if (! $this->rbacReady()) {
            return $next($request);
        }

        abort(403, "Akses modul {$module} tidak diberikan untuk role Anda.");
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
