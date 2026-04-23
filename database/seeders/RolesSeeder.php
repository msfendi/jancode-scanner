<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => 'Admin', 'guard_name' => 'web'],
            []
        );
        DB::table('roles')->updateOrInsert(
            ['name' => 'User', 'guard_name' => 'web'],
            []
        );
    }
}
