<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Aturan khusus import & scan JANCODE

### 1. Case mix jancode (2 jancode dalam 1 karton)
Jika di Excel dua jancode berada pada satu karton yang sama (sel nomor karton
di-merge), keduanya **tetap diimport sebagai satu baris** dengan kode:

```
4550725146768-4550725146769
```

Operator tetap bisa scan **salah satu** jancode (atau kode lengkapnya) dan
sistem akan mencocokkannya ke baris tersebut.

### 2. Karton broken (qty pcs tidak penuh)
Jika satu jancode muncul lebih dari sekali dalam satu sheet dan salah satu
barisnya berisi **pcs per karton di bawah ambang** (default 60), baris itu
dianggap karton broken dan kodenya diberi akhiran **`B`**:

```
4550725100001B
```

Ambang dapat diubah lewat `.env`:

```env
BROKEN_PCS_THRESHOLD=60
```

Kode broken tetap bisa discan (exact match) dan dipisahkan dari jancode normal.

### 3. Surat jalan per submit (partial scan)
Setiap kali **Submit** batch scan, operator wajib mengisi **kode surat jalan**
(opsional: catatan). Setiap submit = satu kedatangan, tersimpan di
`scan_batches.surat_jalan` dan tampil di halaman **Riwayat Batch Scan**
(`/scanner/batches`) beserta filter session/tanggal.

### 4. Assort (1 jancode, beberapa warna / size)
Assort di Excel bisa berupa **1 baris** (beberapa size dalam satu karton) atau
**2 baris / lebih** (beberapa warna dalam karton yang sama, sel JAN & nomor
karton di-merge). Keduanya diimport sebagai **SATU baris data**:

- **color** = warna digabung dengan `;` → `000: WHITE; 002: LIGHT GRAY`
- **size** = size digabung dengan `/` → `S/M/L/XL`
- **qty per karton** = total pcs semua warna/size dalam 1 karton (mis. 60)
- **qty karton** = qty karton pada sel yang di-merge (tidak dijumlahkan)
- **total pcs** = qty karton × qty per karton

Karena tetap satu jancode, scan & penempatan pallet berjalan seperti biasa.
Baris TOTAL / GRAND TOTAL tetap dilewati, dan JAN yang tidak valid (bukan 13
digit) tetap ditandai gagal.

### 5. Layout pallet (BR-07)
Row **B–I**, kolom **a & b = 14 posisi**, kolom **c & d = 19 posisi**
(bertambah dari 16). Total **528 alamat pallet** (8 row × 66 posisi), kapasitas
42 karton per pallet. Penambahan posisi `c-17..c-19` dan `d-17..d-19` dilakukan
oleh migration `2026_10_26_000002_add_extra_cd_pallet_positions` (idempotent).
Halaman `/pallet`, visualisasi 3D, dan display publik otomatis membaca jumlah
posisi dari database.

## RBAC (Role & Permission)

Dua role bawaan (dibuat otomatis oleh migration `2026_10_22_000001_seed_rbac_roles`):

| Role | Akses |
|---|---|
| **Super Admin** (`superadmin`) | Semua fitur, termasuk **RBAC**, **User Management**, dan **hapus permanen session** |
| **CTPAT** (`ctpat`) | Semua fitur operasional, **KECUALI** RBAC, User Management, dan hapus permanen session |

- Definisi permission & role: `app/Support/Rbac.php` (sumber tunggal, **tidak** memakai
  `config:` supaya tidak terpengaruh `config:cache` yang basi).
- Helper: `app/Support/Rbac.php`.
- Model akses route:
  - `module:xxx` → boleh membuka halaman modul (butuh permission `xxx.view` **atau** `xxx.manage`).
  - `can:xxx.manage` → hak mengubah data pada modul tersebut (scan, tambah, hapus, finish, dll).
  - `can:rbac.manage` / `can:user.manage` / `can:session.hard-delete` → khusus fitur tersebut.
- Sinkronisasi ulang (mis. setelah menambah permission baru):
  ```bash
  php artisan db:seed --class=RolePermissionSeeder
  ```
- Halaman **Role & Permission** (`/role/index`) berisi matrix permission per role + **peta route → permission**
  (untuk mudah memverifikasi semua route sudah ter-cover). Role `superadmin` & `ctpat` dilindungi dari penghapusan.
- Halaman **Daftar User** menampilkan role tiap user; ikon kunci untuk mengganti role.
- **Hapus permanen session** (Super Admin): tombol tong sampah di daftar Session →
  menghapus session beserta packing list, revisi import, jancode, log scan, batch
  scan, penempatan pallet, dan karton keluar (tidak bisa dibatalkan).

## Deploy (update ke server)

Semua fitur terbaru ada di branch **`submit-pack`**. Setelah `git pull`, **wajib**
bersihkan cache Laravel — kalau tidak, route baru (`/scanner/delta`, `/scanner/qr`,
`/scanner/join`) akan error **"Route [scanner.delta] not defined"**:

```bash
git pull origin submit-pack
composer install --no-dev --optimize-autoloader
php artisan package:discover --ansi   # regenerasi manifest package (WAJIB)
php artisan optimize:clear            # route:cache + view:cache + config:cache + cache:clear
php artisan migrate --force
php artisan view:cache
```

> **Kenapa `package:discover` wajib?**
> `bootstrap/cache/packages.php` adalah manifest package yang otomatis dibuat
> Composer. Manifest itu **tidak ikut ter-commit** (masih di `.gitignore`), jadi
> di server bisa berisi paket `barryvdh/laravel-debugbar` (dev) sementara
> paketnya sudah dihapus oleh `composer install --no-dev`. Akibatnya aplikasi
> gagal boot dengan:
> `Class "Fruitcake\LaravelDebugbar\ServiceProvider" not found`.
> `php artisan package:discover --ansi` menulis ulang manifest tanpa paket dev.
> Alternatif: `rm -f bootstrap/cache/packages.php bootstrap/cache/services.php`.

> **Kenapa perlu `optimize:clear`?**
> `php artisan route:cache` menyimpan daftar route ke `bootstrap/cache/routes-v7.php`.
> Cache itu tidak otomatis invalidated oleh `git pull`, sehingga route yang baru
> ditambahkan tidak dikenal sampai cache di-clear. `php artisan route:clear`
> saja juga cukup untuk kasus ini.
>
> app sudah punya pengaman: middleware `role` (alias `role:Admin` versi lama)
> memetakan role `Admin` → `superadmin`, dan kalau tabel role belum ter-seed
> (migration belum dijalankan) tidak ada user yang terkunci. Selain itu, middleware
> `PurgeStaleCaches` otomatis menghapus cache route/config yang lebih lama dari
> source-nya (route baru langsung terbaca setelah 1 request). Tapi **tetap
> jalankan `php artisan optimize:clear` setelah pull**.

> **Permission folder di server Linux (nginx/apache)**
> `storage/` dan `bootstrap/cache/` **wajib bisa ditulis** oleh user web server.
> Kalau proses `composer install` / `artisan` dijalankan dengan user lain
> (misal root atau user deploy), file hasil build jadi milik user itu dan
> aplikasi gagal boot dengan:
>
> - `The stream or file ".../storage/logs/laravel.log" could not be opened in append mode: Permission denied`
> - `The ".../bootstrap/cache" directory must be present and writable`
> - `Script @php artisan package:discover --ansi handling the post-autoload-dump event returned with error code 1`
>
> Perbaikannya (sesuaikan `www-data` dengan user web server Anda: `nginx`,
> `apache`, `www-data`, …):
>
> ```bash
> cd /var/www/jancode-scanner
>
> # 1. Pastikan foldernya ada
> mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
>          storage/logs bootstrap/cache
>
> # 2. Beri hak tulis ke user web server
> sudo chown -R www-data:www-data storage bootstrap/cache
> sudo chmod -R 775 storage bootstrap/cache
> sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
> sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
>
> # 3. Ulangi deploy (jalankan SETELAH chown agar file tidak jadi milik user lain)
> composer install --no-dev --optimize-autoloader
> php artisan package:discover --ansi
> php artisan optimize:clear
> php artisan migrate --force
> php artisan view:cache
> ```
>
> Tips: kalau tidak bisa `sudo`, jalankan `composer install` & `artisan` memakai
> user yang sama dengan user web server (`sudo -u www-data composer install ...`).

> **Catatan server lokal (Windows)**
> Demo dengan `php artisan serve` berjalan **single worker** di Windows, sehingga
> koneksi SSE yang panjang menahan request lain. Live update Scanner sudah
> diubah ke *delta polling* (request pendek) supaya scan dari device lain tidak
> tertunda. Kalau tetap memakai `artisan serve`, jalankan lewat Apache/Nginx
> agar request bisa diproses paralel.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
