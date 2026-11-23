<?php

use App\Support\Rbac;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkronkan RBAC setelah menambah permission modul lokasi.
 *
 * Tanpa langkah ini, `module:location` akan menolak semua user karena
 * permission `location.view` belum ada di database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        Rbac::sync();
    }

    public function down(): void
    {
        //
    }
};