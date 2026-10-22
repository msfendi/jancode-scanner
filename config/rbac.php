<?php

/**
 * Definisi RBAC (sumber tunggal untuk permission, role, seeder, dan UI).
 *
 * Role yang tersedia:
 *  - superadmin : akses penuh (termasuk RBAC, User Management, hapus session)
 *  - ctpat       : akses semua fitur operasional, KECUALI RBAC, User
 *                  Management, dan hapus permanen session.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Permission per modul
    |--------------------------------------------------------------------------
    | Dikelompokkan sesuai bagian di sidebar agar mudah dibaca di halaman RBAC.
    */
    'groups' => [

        'Dashboard' => [
            'dashboard.view' => 'Lihat Dashboard',
        ],

        'Sesi Import' => [
            'session.view'         => 'Lihat & pilih session',
            'session.manage'       => 'Mulai / tutup / buka session',
            'session.hard-delete'  => 'Hapus permanen session (hard delete)',
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Role
    |--------------------------------------------------------------------------
    | "exclude" = permission yang TIDAK diberikan ke role tersebut.
    */
    'roles' => [

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
    ],

    /*
    |--------------------------------------------------------------------------
    | Role default untuk user baru
    |--------------------------------------------------------------------------
    */
    'default_role' => 'ctpat',

    /*
    |--------------------------------------------------------------------------
    | Role yang tidak boleh dihapus dari UI
    |--------------------------------------------------------------------------
    */
    'protected_roles' => ['superadmin', 'ctpat'],

];
