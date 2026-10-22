<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Helper RBAC: sumber tunggal untuk permission, role, dan sinkronisasi.
 *
 * Definisi disimpan di class ini (bukan di config/) supaya tidak terpengaruh
 * `config:cache` yang basi di server.
 */
class Rbac
{
    /**
     * Permission per modul (sesuai bagian di sidebar).
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'Lihat Dashboard',
            ],

            'Sesi Import' => [
                'session.view'        => 'Lihat & pilih session',
                'session.manage'      => 'Mulai / tutup / buka session',
                'session.hard-delete' => 'Hapus permanen session (hard delete)',
            ],

            'Packing List' => [
                'packing-list.view'   => 'Lihat daftar packing list',
                'packing-list.manage' => 'Import, manage, finish & hapus packing list',
            ],

            'Carton Inbound' => [
                'scan.view'   => 'Lihat halaman scan jancode',
                'scan.manage' => 'Scan, submit, rollback & riwayat batch scan',
            ],

            'Carton Outbound' => [
                'carton-out.view'   => 'Lihat carton outbound',
                'carton-out.manage' => 'Proses karton keluar',
            ],

            'Manage Pallet' => [
                'pallet.view'   => 'Lihat layout & detail pallet',
                'pallet.manage' => 'Tempatkan / pindahkan / kosongkan pallet',
            ],

            'Modul Hangtag' => [
                'hangtag.view'   => 'Lihat data hangtag',
                'hangtag.manage' => 'Kelola data & scan hangtag',
            ],

            'User Management' => [
                'user.manage' => 'Kelola user (daftar, buat, edit, assign role)',
            ],

            'RBAC' => [
                'rbac.manage' => 'Kelola role & permission',
            ],
        ];
    }

    /**
     * Definisi role.
     *
     * @return array<string, array{label:string, description:string, exclude:array<int,string>}>
     */
    public static function roles(): array
    {
        return [
            'superadmin' => [
                'label'       => 'Super Admin',
                'description' => 'Akses penuh seluruh fitur, termasuk RBAC, User Management, dan hapus permanen session.',
                'exclude'     => [],
            ],

            'ctpat' => [
                'label'       => 'CTPAT',
                'description' => 'Akses seluruh fitur operasional, kecuali RBAC, User Management, dan hapus permanen session.',
                'exclude'     => ['rbac.manage', 'user.manage', 'session.hard-delete'],
            ],
        ];
    }

    /**
     * Role yang tidak boleh dihapus dari UI.
     *
     * @return array<int, string>
     */
    public static function protectedRoles(): array
    {
        return array_keys(self::roles());
    }

    public static function defaultRole(): string
    {
        return 'ctpat';
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
        $definition = self::roles()[$role] ?? null;

        if (! $definition) {
            return [];
        }

        $exclude = (array) ($definition['exclude'] ?? []);

        return array_values(array_diff(self::allPermissions(), $exclude));
    }

    public static function roleLabel(string $role): string
    {
        return (string) (self::roles()[$role]['label'] ?? $role);
    }

    public static function roleDescription(string $role): string
    {
        return (string) (self::roles()[$role]['description'] ?? '');
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

        if ($existing && array_key_exists($existing, self::roles())) {
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
        foreach (self::roles() as $name => $definition) {
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
                ->whereNotIn('name', array_keys(self::roles()))
                ->delete();
        }

        return $counts;
    }
}
