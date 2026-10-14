# PRD v2 — Jancode Scanner & Pallet Placement

Oct 14, 2026 · @Fendi

## 1. Ringkasan dan cara memakai dokumen

Aplikasi **Jancode Scanner** yang sudah berjalan ditambah tiga kemampuan: import packing list Excel (dengan AI) ke master jancode, penempatan karton ke alamat pallet (manual dan otomatis), dan visualisasi layout pallet beserta progres scan.

**Tujuan**

- Operator tidak lagi menginput jancode satu per satu.
- Operator tahu karton harus ditaruh di alamat pallet mana, tanpa melanggar kapasitas dan aturan pengelompokan.
- Supervisor melihat kondisi pallet, progres scan, dan kekurangan (balance) dalam satu layar.

**Cara memakai dokumen ini (untuk agent)**

- Kerjakan per fase di bagian 3. Jangan lompat fase.
- Setiap aturan punya ID (`BR-xx`) dan setiap kebutuhan punya ID (`FR-xx`). Rujuk ID itu di kode, test, dan laporan.
- Tanda **\[ASUMSI\]** berarti nilai default yang dipakai sampai dikonfirmasi. Tanda **\[KONFIRMASI\]** berarti jangan mengimplementasikan sebelum ada jawaban, kecuali memakai default yang tertulis.
- Jangan menulis ulang project. Ikuti stack, konvensi, auth/role Admin, dan alur scan yang sudah ada.

## 2. Glosarium

Satu istilah = satu arti. Pakai nama kolom/field di kanan pada kode dan UI internal.

| Istilah | Arti | Nama field |
| --- | --- | --- |
| Packing list (PL) | File Excel dari pabrik/buyer berisi isi tiap carton | — |
| Item | Satu baris master jancode: kombinasi OCD + style + color + size | `master_jancode` |
| JAN code | Kode 13 digit pada barcode karton | `jan_code` |
| OCD | Nomor order (CPO), mis. `26139` | `ocd` |
| Country | Negara tujuan (DESTINATION), dinormalisasi huruf besar | `country` |
| SOLID | Carton berisi satu JAN/size/color | — |
| ASSORT | Carton berisi campuran size/color (satu JAN mewakili banyak varian) | — |
| Carton merge | Carton yang dipakai bersama beberapa baris di Excel (sel carton di-merge) | — |
| Alamat pallet | Satu posisi fisik: row (B–I) + kolom (a/b/c/d) + nomor posisi | `pallet_address` |
| Penempatan | Relasi antara item dan alamat pallet beserta jumlah karton | `pallet_assignment_detail` |
| Packing list qty | Jumlah karton menurut packing list | `packing_list_qty_carton` |
| Receive qty | Jumlah karton yang sudah di-scan | `receive_qty_carton` |
| Balance | Sisa karton belum di-scan | `balance` |

`balance = packing_list_qty_carton − receive_qty_carton`.

## 3. Fase dan scope

Pekerjaan dibagi lima fase. Setiap fase harus selesai, lolos acceptance criteria-nya, dan direview sebelum fase berikutnya.

| Fase | Isi | Bergantung pada |
| --- | --- | --- |
| P1 | Master jancode baru + import packing list dengan AI (SOLID saja) | — |
| P2 | Master alamat pallet (480 posisi) + penempatan manual | P1 |
| P3 | Auto placement (Preview dan Apply) | P2 |
| P4 | Visualisasi layout pallet + ringkasan kapasitas dan scan | P2 |
| P5 | Penyesuaian alur scan terhadap master baru | P1 |

P3 dan P4 bisa dikerjakan paralel setelah P2.

**Di luar scope**

- Import baris ASSORT dan carton merge (sengaja dilewati pada versi ini).
- Sheet "Cutting Instruction - Assort" pada file Excel.
- Ubah desain autentikasi/role; fitur ini hanya untuk role Admin yang sudah ada.
- Optimasi jarak/rute fisik forklift pada auto placement.

## 4. Aturan bisnis

Semua aturan wajib ditegakkan **di server** (bukan hanya di UI) dan punya test.

| ID | Aturan | Fase |
| --- | --- | --- |
| BR-01 | Hanya baris SOLID yang diimport; ASSORT PACK diabaikan. | P1 |
| BR-02 | Baris dengan carton merge (sel carton mencakup lebih dari satu baris) tidak diimport. | P1 |
| BR-03 | Baris diimport hanya jika punya JAN 13 digit, tepat satu size terisi, dan total carton bilangan bulat > 0. | P1 |
| BR-04 | Nilai carton bersumber dari packing list. Jika ambigu, tandai untuk ditinjau; jangan mengarang kuantitas. | P1 |
| BR-05 | Baris dilewati wajib tercatat beserta nomor baris Excel dan alasannya. | P1 |
| BR-06 | `receive_qty_carton` awal = 0; `balance` = `packing_list_qty_carton` − `receive_qty_carton`. | P1 |
| BR-07 | Layout pallet: row B–I (8 row); tiap row punya kolom a=14, b=14, c=16, d=16 posisi. Total 480 posisi. | P2 |
| BR-08 | Satu posisi = satu alamat pallet. Maksimum isi satu alamat = 42 karton. | P2 |
| BR-09 | Satu alamat hanya boleh berisi item dengan `style` **dan** `country` yang sama. | P2 |
| BR-10 | Item berbeda `color`/`size` boleh berbagi alamat jika BR-08 dan BR-09 terpenuhi. | P2 |
| BR-11 | Penempatan yang membuat total karton alamat > 42 harus ditolak dengan pesan jelas. | P2 |
| BR-12 | Total karton yang ditempatkan untuk satu item tidak boleh melebihi `packing_list_qty_carton`. | P2 |
| BR-13 | Preview auto placement tidak mengubah data. Apply menyimpan semua hasil dalam satu transaksi. | P3 |
| BR-14 | Alamat kosong berwarna hijau; alamat terisi berwarna merah. | P4 |
| BR-15 | Nilai `country` dinormalisasi (trim, uppercase) sebelum dibandingkan atau dikelompokkan. | P1 |

BR-12 adalah tambahan baru. PRD lama tidak mencegah penempatan melebihi jumlah di packing list.

## 5. Model data

Nama tabel/kolom di bawah adalah usulan; sesuaikan dengan konvensi project yang ada. Kolom teknis (id, created\_at, updated\_at, created\_by) berlaku untuk semua tabel.

**`master_jancode`** — satu baris per item (OCD + style + color + size)

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `country`, `buyer`, `ocd`, `style`, `color`, `size` | text | Sumber: header dan kolom Excel; lihat dokumen PRD import |
| `jan_code` | text(13) | Identitas scan **\[KONFIRMASI\]** |
| `packing_list_qty_carton` | int ≥ 0 | Dari kolom TOTAL CARTON |
| `receive_qty_carton` | int ≥ 0 | Default 0, naik saat scan |
| `balance` | int | Dihitung dari dua kolom di atas |

Unique key: `(ocd, style, color, size, jan_code)`.

**`pallet_address`** — 480 baris, dibuat otomatis (seed) dari BR-07

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `row_label` | char | B sampai I |
| `col_label` | char | a, b, c, d |
| `position_no` | int | a/b: 1–14; c/d: 1–16 |
| `code` | text unik | Format **\[ASUMSI\]** `B-a-01` |
| `max_carton` | int | 42 |

**`pallet_assignment`** — satu baris per alamat yang terisi

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `pallet_address_id` | FK unik | Satu assignment aktif per alamat |
| `style`, `country` | text | Dikunci saat assignment pertama (BR-09) |
| `total_carton` | int | Jumlah detail; ≤ 42 |

**`pallet_assignment_detail`**

| Kolom | Tipe | Catatan |
| --- | --- | --- |
| `assignment_id` | FK |  |
| `master_jancode_id` | FK | Mengacu item, bukan menyalin data |
| `qty_carton` | int > 0 |  |

**Scan log** memakai tabel yang sudah ada. Tambahkan relasi ke `master_jancode_id` bila belum ada.

## 6. Kebutuhan fungsional

| ID | Modul | Kebutuhan | Aturan terkait |
| --- | --- | --- | --- |
| FR-01 | Import | Upload `.xlsx`; sistem membaca sheet pertama dan header (BUYER, STYLE, DESTINATION, CPO). | BR-15 |
| FR-02 | Import | AI mengekstrak baris yang valid dan daftar baris dilewati; output JSON sesuai schema. Kode memvalidasi ulang hasil AI. | BR-01–05 |
| FR-03 | Import | Layar preview: ringkasan, tabel hasil, tab "Dilewati" beserta alasan, peringatan duplikat. Simpan setelah konfirmasi, transaksional. | BR-05, BR-06 |
| FR-04 | Pallet | Seed 480 alamat sesuai layout; halaman daftar/cari alamat. | BR-07, BR-08 |
| FR-05 | Manual | Pilih item + alamat + jumlah karton; sistem memvalidasi dan menolak pelanggaran dengan pesan jelas. | BR-08–12 |
| FR-06 | Manual | Ubah jumlah, pindahkan, atau hapus penempatan. Total tetap konsisten. | BR-08–12 |
| FR-07 | Auto | Filter style/country; mode Preview (tanpa simpan) dan Apply (transaksi). | BR-13 |
| FR-08 | Auto | Ringkasan hasil: jumlah item, alamat terpakai, total karton, kapasitas terpakai, item yang tidak bisa ditempatkan beserta alasan. | BR-13 |
| FR-09 | Visual | Grid row B–I horizontal; depan = a/b, belakang = c/d; hijau kosong, merah terisi. | BR-14 |
| FR-10 | Visual | Klik alamat menampilkan detail: style, country, JAN, color, size, karton. | — |
| FR-11 | Visual | Ringkasan: total posisi, terisi, total karton, kapasitas sisa; breakdown per size; total scanned dan balance. | BR-06 |
| FR-12 | Visual | Data scan dan balance diperbarui otomatis setiap 10 detik **\[ASUMSI\]**. | — |
| FR-13 | Scan | Alur scan yang ada dipertahankan; scan menaikkan `receive_qty_carton`, balance turun. Scan melebihi packing list ditolak/diperingatkan **\[KONFIRMASI\]**. | BR-06 |

Semua modul Admin-only mengikuti auth yang ada. Setiap aksi tulis (import, penempatan, apply) dicatat di audit log: user, waktu, ringkasan perubahan.

## 7. Algoritma auto placement

PRD lama hanya menyebut tujuan; langkah di bawah adalah default yang bisa langsung diimplementasikan **\[ASUMSI\]** dan bisa diganti setelah konfirmasi.

**Input:** filter opsional (style, country). **Output:** daftar penempatan usulan + daftar item gagal. Preview dan Apply memakai fungsi yang sama; Apply hanya menyimpan hasilnya.

1. Ambil item yang masih punya sisa belum ditempatkan: `sisa = packing_list_qty_carton − total ditempatkan`, `sisa > 0`, sesuai filter.
2. Kelompokkan per `(style, country)`. Urutkan grup berdasarkan total sisa menurun.
3. Dalam tiap grup, urutkan item berdasarkan sisa menurun (item besar dulu), lalu `color`, lalu `size` agar hasil stabil.
4. Siapkan alamat: pertama alamat yang **sudah dikunci** ke grup itu dan masih punya sisa kapasitas; kemudian alamat kosong, urut `row` B→I, lalu kolom a, b, c, d, lalu `position_no`.
5. Untuk tiap item: isi alamat yang sisa kapasitasnya > 0 dengan `min(sisa item, sisa kapasitas alamat)`. Jika item belum habis, lanjut ke alamat berikutnya (item boleh dipecah ke beberapa alamat).
6. Item kecil yang muat di sisa kapasitas alamat grup yang sama digabung ke alamat itu sebelum membuka alamat baru.
7. Jika alamat kosong habis, item/sisanya masuk daftar gagal dengan alasan `NO_CAPACITY`.
8. Tidak pernah melanggar BR-08, BR-09, BR-12. Alamat yang dikunci style/country lain tidak dipakai.
9. Hasil: tiap alamat dengan isi, total karton, dan kapasitas terpakai; ringkasan jumlah item, alamat terpakai, total karton, item gagal.

Apply: jalankan ulang validasi BR-08/09/11/12 terhadap kondisi data terbaru, simpan semuanya dalam satu transaksi, dan batalkan seluruhnya jika ada satu pelanggaran.

## 8. Acceptance criteria

Setiap baris bisa dijadikan satu test otomatis.

| ID | Given | When | Then |
| --- | --- | --- | --- |
| AC-01 | File contoh `Packing_List_AB307A7S_THAILAND_QTY_26139` | Import dijalankan | 16 baris diimport, total 36 karton; tidak ada baris ASSORT atau carton merge (BR-01, 02) |
| AC-02 | Hasil import | Dibuka tab Dilewati | Setiap baris yang dilewati punya nomor baris dan alasan (BR-05) |
| AC-03 | Item baru dari import | Disimpan | `receive_qty_carton = 0`, `balance = packing_list_qty_carton` (BR-06) |
| AC-04 | Database kosong pallet | Seed dijalankan | Ada 480 alamat: tiap row B–I punya a=14, b=14, c=16, d=16 (BR-07) |
| AC-05 | Alamat berisi 40 karton | Admin menambah 3 karton | Ditolak dengan pesan kapasitas; data tidak berubah (BR-08, 11) |
| AC-06 | Alamat berisi style X / country Thailand | Admin menempatkan style Y atau country lain | Ditolak dengan pesan kompatibilitas (BR-09) |
| AC-07 | Dua item beda color, style dan country sama | Ditempatkan ke satu alamat, total ≤ 42 | Berhasil (BR-10) |
| AC-08 | Item dengan packing list 5 karton | Admin menempatkan total 6 karton | Ditolak (BR-12) |
| AC-09 | Item belum ditempatkan | Auto placement Preview | Hasil tampil; tabel penempatan di database tidak berubah (BR-13) |
| AC-10 | Hasil Preview yang sudah ditinjau | Apply | Tersimpan lengkap; jika satu validasi gagal, tidak ada yang tersimpan |
| AC-11 | Kapasitas alamat habis untuk seluruh grup | Auto placement | Item sisa muncul di daftar gagal dengan alasan `NO_CAPACITY` |
| AC-12 | Layout pallet dibuka | Tampilan dimuat | Row B–I horizontal, a/b depan, c/d belakang; kosong hijau, terisi merah (BR-14) |
| AC-13 | Scan karton item | Scan berhasil | Receive +1, balance −1 di visualisasi dalam ≤ 10 detik (FR-12) |
| AC-14 | Input `country` "thailand " dan "THAILAND" | Disimpan/dikelompokkan | Diperlakukan sebagai grup yang sama (BR-15) |

## 9. Open questions dan default sementara

Jika belum dijawab, agent memakai kolom Default dan menandainya di laporan fase.

| # | Pertanyaan | Default sementara |
| --- | --- | --- |
| Q1 | Satu alamat = satu posisi (480 alamat), atau alamat = row + kolom (32 alamat) dengan banyak posisi? | Satu posisi = satu alamat |
| Q2 | Format kode alamat | `B-a-01` |
| Q3 | ASSORT dan carton merge tidak diimport (keputusan baru). Apakah scan identity khusus untuk JAN yang sama di beberapa varian masih perlu? | Tidak perlu; JAN unik per item SOLID |
| Q4 | Item boleh dipecah ke beberapa alamat? | Boleh |
| Q5 | Urutan pengisian alamat pada auto placement (row, kolom) | B→I, a→d, posisi naik |
| Q6 | Scan melebihi `packing_list_qty_carton`: tolak atau izinkan dengan peringatan? | Tolak dengan pesan |
| Q7 | Alamat terisi sebagian vs penuh perlu warna berbeda? | Tidak; hanya hijau dan merah (BR-14) |
| Q8 | Hapus/pindah penempatan setelah ada scan: diizinkan? | Diizinkan, tercatat di audit log |
| Q9 | `ocd` = angka CPO saja atau teks penuh? | Angka saja (`26139`) |
| Q10 | `jan_code` tetap kolom master? | Ya |
| Q11 | Data master lama: migrasi, arsip, atau kosongkan? | Arsip, jangan hapus |

## 10. Instruksi untuk Antigravity

Salin blok ini ke Antigravity bersama dokumen. Setelah tiap fase agent harus berhenti dan melapor.

```markdown
Konteks: project Jancode Scanner sudah berjalan. Implementasikan PRD v2 ini per fase.
JANGAN menulis ulang project. Ikuti stack, konvensi, auth Admin, dan alur scan yang ada.
Rujuk ID aturan (BR-xx), kebutuhan (FR-xx), dan acceptance criteria (AC-xx) di kode,
test, dan laporan. Semua aturan bisnis ditegakkan di server.

FASE 0 - Audit (tanpa ubah kode)
- Petakan model/tabel, endpoint, layar, integrasi AI, dan alur scan yang ada.
- Tulis rencana perubahan per file dan daftar risiko. Tunggu persetujuan.

FASE P1 - Master jancode + import AI (FR-01..03, BR-01..06, BR-15, AC-01..03, AC-14)
FASE P2 - Pallet master + penempatan manual (FR-04..06, BR-07..12, AC-04..08)
FASE P3 - Auto placement (FR-07..08, BR-13, bagian 7, AC-09..11)
FASE P4 - Visualisasi layout (FR-09..12, BR-14, AC-12..13)
FASE P5 - Penyesuaian scan (FR-13)

Di tiap fase:
1. Tulis migrasi (up & down), model, service, endpoint, UI.
2. Tulis test untuk setiap AC fase itu; test tidak memanggil AI sungguhan (mock).
3. Jalankan semua test; laporkan hasil, file yang berubah, dan asumsi yang dipakai.

Batasan: jangan hardcode API key; jangan hapus data lama tanpa konfirmasi;
jangan import ASSORT atau carton merge; jawab [KONFIRMASI] dengan default bagian 9
lalu catat di laporan.
```

## 11. Perubahan dari v1

| Temuan di v1 | Perbaikan di v2 |
| --- | --- |
| Import ASSORT dan SOLID, bertentangan dengan keputusan terbaru (hanya SOLID, lewati carton merge) | BR-01, BR-02; ASSORT/merge dipindah ke out of scope |
| Syarat identitas scan untuk JAN sama beda varian | Dipertanyakan di Q3, karena SOLID sudah unik per JAN |
| Struktur tabel master tidak dirinci | Bagian 5 dengan kolom 9 field baru |
| "Alamat pallet" dan "posisi" dipakai bergantian (480 vs 42) | Didefinisikan di glosarium dan BR-08; Q1 |
| Auto placement hanya berupa tujuan | Algoritma langkah demi langkah (bagian 7) |
| Tidak ada batas penempatan terhadap jumlah packing list | BR-12 |
| Istilah campur: OCF, CPO, referensi, jancode/item | Glosarium; `ocd` sebagai nama baku |
| "Hampir real-time" tanpa angka | FR-12: 10 detik |
| Acceptance criteria umum, tidak bisa dites | 14 kriteria Given/When/Then (AC-01..14) |
| Tidak ada fase atau urutan kerja | Bagian 3 dan 10 |
| Catatan tentang percakapan dan troubleshooting (bagian 9–10 lama) | Dihapus; tidak relevan untuk agent |
| Tidak ada audit log, error handling, status gagal | Disebut di FR dan AC |
