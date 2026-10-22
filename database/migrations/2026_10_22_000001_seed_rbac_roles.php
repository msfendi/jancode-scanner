<?php

use App\Support\Rbac;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Seed RBAC: permission, role (superadmin & ctpat), dan pemetaan user.
 *
 * Dijalankan otomatis saat deploy (migrate) supaya role & permission selalu
 * tersedia tanpa langkah manual tambahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Lewati bila tabel permission belum ada (mis. fresh install).
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        Rbac::sync(removeLegacyRoles: true);
    }

    public function down(): void
    {
        //
    }
};
