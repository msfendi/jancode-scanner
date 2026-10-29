<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan warna pallet per negara / buyer, disimpan di DB supaya bisa diubah
 * tanpa deploy ulang. Warna dipakai oleh board 2D admin dan display publik.
 *
 * status: full (42/42), partial (1-41), empty (0)
 * country: JAPAN / CHINA / TAIWAN; null = berlaku untuk semua negara
 * buyer: MUJI; null = berlaku untuk semua buyer
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pallet_color_rules')) {
            Schema::create('pallet_color_rules', function (Blueprint $table) {
                $table->id();
                $table->string('group_key', 30);
                $table->string('group_label', 60);
                $table->string('buyer', 30)->nullable();
                $table->string('country', 30)->nullable();
                $table->string('status', 10); // full | partial | empty
                $table->string('color', 7);
                $table->string('text_color', 7)->default('#ffffff');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['group_key', 'status']);
            });
        }

        // Seed hanya saat tabel benar-benar kosong supaya perubahan warna
        // yang sudah dilakukan user tidak tertimpa.
        if (DB::table('pallet_color_rules')->count() > 0) {
            return;
        }

        $now = now();
        $rules = [
            // Japan — biru
            [
                'group_key' => 'japan', 'group_label' => 'Japan',
                'buyer' => 'MUJI', 'country' => 'JAPAN',
                'status' => 'full', 'color' => '#1e40af', 'text_color' => '#eff6ff',
                'sort_order' => 10,
            ],
            [
                'group_key' => 'japan', 'group_label' => 'Japan',
                'buyer' => 'MUJI', 'country' => 'JAPAN',
                'status' => 'partial', 'color' => '#60a5fa', 'text_color' => '#0f172a',
                'sort_order' => 11,
            ],

            // China — merah
            [
                'group_key' => 'china', 'group_label' => 'China',
                'buyer' => 'MUJI', 'country' => 'CHINA',
                'status' => 'full', 'color' => '#991b1b', 'text_color' => '#fef2f2',
                'sort_order' => 20,
            ],
            [
                'group_key' => 'china', 'group_label' => 'China',
                'buyer' => 'MUJI', 'country' => 'CHINA',
                'status' => 'partial', 'color' => '#f472b6', 'text_color' => '#500724',
                'sort_order' => 21,
            ],

            // Taiwan — hijau
            [
                'group_key' => 'taiwan', 'group_label' => 'Taiwan',
                'buyer' => 'MUJI', 'country' => 'TAIWAN',
                'status' => 'full', 'color' => '#166534', 'text_color' => '#f0fdf4',
                'sort_order' => 30,
            ],
            [
                'group_key' => 'taiwan', 'group_label' => 'Taiwan',
                'buyer' => 'MUJI', 'country' => 'TAIWAN',
                'status' => 'partial', 'color' => '#4ade80', 'text_color' => '#052e16',
                'sort_order' => 31,
            ],

            // Buyer MUJI, negara selain Japan/China/Taiwan — oranye
            [
                'group_key' => 'muji_other', 'group_label' => 'MUJI - Negara Lain',
                'buyer' => 'MUJI', 'country' => null,
                'status' => 'full', 'color' => '#c2410c', 'text_color' => '#fff7ed',
                'sort_order' => 40,
            ],
            [
                'group_key' => 'muji_other', 'group_label' => 'MUJI - Negara Lain',
                'buyer' => 'MUJI', 'country' => null,
                'status' => 'partial', 'color' => '#fb923c', 'text_color' => '#431407',
                'sort_order' => 41,
            ],

            // Pallet kosong — netral, tanpa warna negara
            [
                'group_key' => 'empty', 'group_label' => 'Kosong',
                'buyer' => null, 'country' => null,
                'status' => 'empty', 'color' => '#e5e7eb', 'text_color' => '#374151',
                'sort_order' => 50,
            ],
        ];

        foreach ($rules as &$rule) {
            $rule['is_active'] = true;
            $rule['created_at'] = $now;
            $rule['updated_at'] = $now;
        }
        unset($rule);

        DB::table('pallet_color_rules')->insert($rules);
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_color_rules');
    }
};
