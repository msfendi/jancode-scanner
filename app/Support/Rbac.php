<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Helper RBAC: sumber tunggal untuk permission, role, dan sinkronisasi.
 */
class Rbac
{
    /**
     * @return array<string, array<string, string>> grup => permission => label
     */
    public static function groups(): array
    {
        return (array) config('rbac.groups', []);
    }

    /**
     * Semua nama permission (flat).
     *
     * @return array<int, string>
     */
    public static function allPermissions(): array
    {
        $names = [];

        foreach (self::groups() as $permissions) {
            foreach (array_keys($permissions) as $name) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Permission milik sebuah role.
     *
     * @return array<int, string>
     */
    public static function permissionsFor(string $role): array
    {
        $definition = config('rbac.roles.' . $role);

        if (! $definition) {
            return [];
        }

        $all     = self::allPermissions();
        $exclude = (array) ($definition['exclude'] ?? []);

        return array_values(array_diff($all, $exclude));
    }

    public static function roleLabel(string $role): string
    {
        return (string) (config('rbac.roles.' . $role . '.label') ?? $role);
    }

    public static function roleDescription(string $role): string
    {
        return (string) (config('rbac.roles.' . $role . '.description') ?? '');
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return $user ? $user->hasRole('superadmin') : false;
    }

    /**
     * Role yang diberikan ke user (prioritas: role yang sudah ada).
     */
    public static function targetRoleFor(User $user): string
    {
        $existing = $user->roles->pluck('name')->first();

        if ($existing && array_key_exists($existing, (array) config('rbac.roles', []))) {
            return $existing;
        }

        if (str_contains(strtolower((string) $user->email), 'ctpat')) {
            return 'ctpat';
        }

        return 'superadmin';
    }

    /**
     * Sinkronkan permission + role ke database.
     *
     * @param  bool  $removeLegacyRoles  hapus role lama (Admin/User) — dipakai saat migrasi
     * @return array<string, int>
     */
    public static function sync(bool $removeLegacyRoles = false): array
    {
        $counts = [];

        // 1. Permission
        foreach (self::allPermissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // 2. Role + permission-nya
        foreach ((array) config('rbac.roles', []) as $name => $definition) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions(self::permissionsFor($name));
        }

        // 3. User -> role
        foreach (User::with('roles')->get() as $user) {
            $target = self::targetRoleFor($user);
            $user->syncRoles([$target]);
            $counts[$target] = ($counts[$target] ?? 0) + 1;
        }

        // 4. Buang role lama (Admin / User)
        if ($removeLegacyRoles) {
            Role::query()
                ->whereNotIn('name', array_keys((array) config('rbac.roles', [])))
                ->delete();
        }

        return $counts;
    }
}
