<?php

namespace Database\Seeders;

use App\Support\Rbac;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Sinkronkan permission, role (superadmin & ctpat), dan role user.
     *
     * Jalankan ulang setelah menambah permission baru:
     * php artisan db:seed --class=RolePermissionSeeder
     */
    public function run(): void
    {
        $counts = Rbac::sync(removeLegacyRoles: false);

        foreach ($counts as $role => $total) {
            $this->command?->info("Role {$role}: {$total} user");
        }
    }
}
