# PRD — Master JAN Code Import (AI-Assisted, Struktur Baru)

Oct 14, 2026 · @Fendi

## 1. Ringkasan, tujuan, scope

Fitur import Master JAN Code di project **Jancode Scanner** diubah: struktur tabel master diganti ke 9 kolom baru, dan file Excel packing list dibaca oleh AI yang hanya mengambil baris **SOLID** yang cartonnya tidak merge.

**Tujuan**

- Operator upload packing list apa adanya (tanpa merapikan template), lalu sistem mengekstrak data master yang valid.
- Hanya baris SOLID, satu JAN per carton, yang masuk master. ASSORT PACK dan carton campuran (merge) tidak diimport.
- Hasil ekstraksi tampil di layar preview untuk dicek sebelum disimpan.

**In scope**

- Perubahan skema tabel master jancode + migrasi.
- Endpoint dan service import berbasis AI untuk template packing list (contoh: `Packing_List_AB307A7S_THAILAND_QTY_26139`).
- Layar upload, preview, koreksi ringan, dan konfirmasi import.

**Out of scope**

- Sheet "Cutting Instruction - Assort" (sheet kedua di file contoh) tidak dibaca.
- Proses scan/receive di lapangan tidak diubah, kecuali penyesuaian nama field `receive_qty_carton` dan `balance`.
- Import baris ASSORT dan carton campuran (bisa jadi fase berikutnya).

## 2. Struktur tabel master jancode (baru)

Tabel master memakai 9 kolom bisnis berikut, ditambah kolom teknis yang sudah ada di project (id, timestamp, dst).

| Kolom | Tipe | Sumber di Excel | Catatan |
| --- | --- | --- | --- |
| `country` | text | Header `DESTINATION` (D7) | Contoh: THAILAND |
| `buyer` | text | Header `BUYER` (D5) | Contoh: MUJI |
| `ocf` | text | Header `CPO` (D8) | Default: angka sebelum " - " → `26139` (lihat open question) |
| `style` | text | Header `STYLE` (D6) | Contoh: AB307A7S |
| `color` | text | Kolom `COLOR` (D), merge vertikal | Di-forward-fill; dinormalisasi (trim, uppercase) |
| `size` | text | Judul kolom size (G11:L11) yang terisi | XS, S, M, L, XL, 2XL |
| `packing_list_qty_carton` | integer | Kolom `TOTAL CARTON` (N) | Jumlah carton di packing list |
| `receive_qty_carton` | integer | Tidak ada di Excel | Default `0` saat import |
| `balance` | integer | Dihitung | `packing_list_qty_carton − receive_qty_carton`; default = packing\_list\_qty\_carton |

**Kolom `jan_code` tetap dipertahankan** sebagai identitas hasil scan (kolom E `JAN CODE`). Kunci unik yang disarankan: `(ocf, style, color, size, jan_code)`. Jika project saat ini tidak memakai `jan_code` di tabel master, konfirmasi dulu (lihat open questions).

## 3. Analisis template Excel packing list

File contoh punya 2 sheet; hanya sheet pertama (`THAILAND - 26139`, A1:U79) yang dipakai. Sheet kedua adalah cutting instruction assort dan diabaikan.

**Header (baris 5–8):** label di kolom A, nilai di kolom D → BUYER, STYLE, DESTINATION, CPO (`26139 - 266C0127-B5`).

**Tabel data (mulai baris 12):**

| Kolom | Isi |
| --- | --- |
| A–C | Carton no dari – sampai (sering berupa formula, tidak boleh diandalkan) |
| D | `COLOR` (kode + nama, mis. `002: LIGHT GREY`), di-merge vertikal sehingga hanya sel pertama yang terisi |
| E | `JAN CODE`, satu JAN per baris untuk SOLID |
| F | `KETERANGAN`: `ASSORT PACK` atau `SOLID` |
| G–L | `BREAKDOWN SIZE` (XS, S, M, L, XL, 2XL): pcs per carton, size = kolom yang terisi |
| N | `TOTAL CARTON` |

**Pola baris yang ada di file contoh**

1. **ASSORT PACK** (baris 12–25): satu carton berisi banyak warna/size, kolom A, E, F, N di-merge. Diabaikan.
2. **SOLID tunggal** (mis. baris 29–32): satu baris = satu JAN = satu size, kolom N tidak di-merge. **Diimport.**
3. **SOLID carton campuran** (mis. baris 33–34, 41–42, 47–48, 55–58, 63–64, 69–70): dua baris atau lebih berbagi satu carton (kolom A/B/C di-merge). **Dilewati.**
4. **Baris non-data**: `TOTAL`, `QTY`, `TOTAL ASSORT PACK`, `TOTAL SOLID ALL COLOUR`, `GRAND TOTAL`. Diabaikan.

Karena informasi merge tidak terlihat jika sheet hanya diubah ke CSV, parser harus mengirim **penanda merge** per baris ke AI (lihat bagian 5).

## 4. Aturan import

Sebuah baris diimport hanya jika **semua** aturan ini terpenuhi:

| # | Aturan | Cara cek |
| --- | --- | --- |
| R1 | Hanya SOLID | `KETERANGAN` (F) = `SOLID`. Baris `ASSORT PACK` dibuang. |
| R2 | Carton tidak merge | Sel carton (A/B/C/N) pada baris itu tidak termasuk merged range yang mencakup lebih dari 1 baris. Jika merge, baris dibuang. |
| R3 | Punya JAN | Kolom E berisi 13 digit angka. |
| R4 | Tepat satu size | Tepat satu sel di G–L terisi angka > 0. Size = judul kolom itu. |
| R5 | Punya total carton | `TOTAL CARTON` (N) berupa bilangan bulat > 0. |
| R6 | Bukan baris ringkasan | Baris `TOTAL`, `QTY`, `TOTAL ASSORT PACK`, `TOTAL SOLID ALL COLOUR`, `GRAND TOTAL` dibuang. |

**Aturan turunan**

- `color` diambil dengan forward-fill dari sel D di atasnya, karena D di-merge vertikal.
- `country`, `buyer`, `ocf`, `style` diambil dari header sheet dan sama untuk semua baris.
- `receive_qty_carton = 0`, `balance = packing_list_qty_carton`.
- Baris yang dilewati **tidak** dibuang diam-diam: dicatat di daftar `skipped` beserta nomor baris Excel dan alasannya (R1–R6), supaya operator bisa audit di layar preview.
- Duplikat (kunci sama dengan data yang sudah ada) ditandai di preview; perilaku default = update `packing_list_qty_carton` dan jaga `receive_qty_carton`.

## 5. Arsitektur dan alur AI import

AI dipakai untuk memahami layout template yang bisa berubah antar buyer, sedangkan kode yang deterministik menjaga hasilnya tetap benar. Alurnya berurutan:

1. **Upload.** Operator mengunggah `.xlsx`. Server menolak file selain xlsx dan file di atas batas ukuran (usulan 5 MB).
2. **Pra-proses (kode, tanpa AI).** Baca sheet pertama dengan openpyxl atau SheetJS. Untuk setiap baris, hasilkan teks terstruktur berisi nomor baris Excel, nilai sel A–U yang tidak kosong (formula diambil nilai cache-nya), dan flag `carton_merged: true` jika sel A, C, atau N berada dalam merged range multi-baris. Nilai D di-forward-fill dan ditandai `color_inherited`.
3. **Panggil AI.** Kirim hasil langkah 2 beserta system prompt (bagian 6). Minta output JSON mengikuti schema yang tetap (structured output / JSON mode), suhu 0.
4. **Validasi (kode).** Parse JSON, validasi dengan schema, lalu cek ulang R1–R6 secara deterministik terhadap baris sumber. Baris yang lolos AI tapi melanggar aturan dibuang dan dicatat sebagai `skipped` oleh sistem. Cek tambahan: total carton hasil import harus sama dengan jumlah `N` baris SOLID non-merge dari parser.
5. **Preview.** Tampilkan tabel hasil, jumlah baris diimport, jumlah dilewati beserta alasan, dan peringatan (mis. JAN duplikat, warna tidak dikenal).
6. **Konfirmasi dan simpan.** Operator menekan Import; data ditulis dalam satu transaksi (semua berhasil atau tidak sama sekali) dan dicatat di log import (file, user, waktu, jumlah baris).

**Prinsip desain**

- AI tidak pernah menulis langsung ke database; hanya menghasilkan usulan yang divalidasi dan dikonfirmasi.
- Penanda merge dihitung oleh kode karena model tidak bisa melihatnya dari teks sel.
- Penyedia AI dan model mengikuti yang sudah dipakai di project; API key hanya di server (env), tidak di client.

## 6. Prompt AI dan schema output

System prompt (simpan sebagai konstanta/file terpisah agar mudah diubah tanpa deploy ulang kode):

```markdown
Kamu adalah parser packing list garment. Input adalah baris-baris sheet Excel dalam teks terstruktur.
Setiap baris punya: row (nomor baris Excel), cells (kolom -> nilai), carton_merged (true/false),
dan color_inherited (warna dari sel merge di atasnya).

TUGAS: ekstrak data master JAN code dengan aturan berikut.

1. Ambil dari header: buyer, style, country (label DESTINATION), ocf (label CPO).
   ocf = bagian angka sebelum " - " pada nilai CPO.
2. HANYA import baris dengan KETERANGAN = "SOLID". Abaikan semua baris "ASSORT PACK".
3. JANGAN import baris dengan carton_merged = true (carton dipakai bersama beberapa baris).
4. JANGAN import baris TOTAL, QTY, TOTAL ASSORT PACK, TOTAL SOLID ALL COLOUR, GRAND TOTAL.
5. Baris valid harus punya JAN 13 digit, tepat satu size terisi (kolom breakdown size),
   dan TOTAL CARTON bilangan bulat > 0. Jika tidak, masukkan ke skipped.
6. size = judul kolom size yang terisi (XS, S, M, L, XL, 2XL).
7. color = nilai color_inherited, trim spasi, huruf besar, format "KODE: NAMA".
8. packing_list_qty_carton = nilai TOTAL CARTON. Jangan menghitung ulang, jangan menebak.
9. Jangan mengarang data. Jika ragu pada sebuah baris, masukkan ke skipped dengan alasan.
10. Keluarkan HANYA JSON sesuai schema, tanpa teks lain.
```

Schema output yang diwajibkan:

```json
{
  "header": { "buyer": "MUJI", "style": "AB307A7S", "country": "THAILAND", "ocf": "26139" },
  "items": [
    {
      "source_row": 29,
      "jan_code": "4550725164908",
      "color": "000: WHITE",
      "size": "S",
      "packing_list_qty_carton": 3
    }
  ],
  "skipped": [
    { "source_row": 12, "reason": "ASSORT" },
    { "source_row": 33, "reason": "MERGED_CARTON" }
  ]
}
```

Nilai `reason` yang diizinkan: `ASSORT`, `MERGED_CARTON`, `SUMMARY_ROW`, `NO_JAN`, `NO_SIZE`, `MULTI_SIZE`, `NO_CARTON`, `OTHER`. Field `country`, `buyer`, `ocf`, `style` per baris ditambahkan oleh kode dari `header`; `receive_qty_carton` dan `balance` diisi oleh kode, bukan AI.

## 7. Hasil yang diharapkan dari file contoh

Untuk file `Packing_List_AB307A7S_THAILAND_QTY_26139`, import harus menghasilkan **16 baris** dengan total **36 carton**. Semua baris: country THAILAND, buyer MUJI, ocf 26139, style AB307A7S, receive 0, balance = packing list.

| Baris Excel | Color | JAN | Size | Packing list (carton) |
| --- | --- | --- | --- | --- |
| 29 | 000: WHITE | 4550725164908 | S | 3 |
| 30 | 000: WHITE | 4550725164915 | M | 1 |
| 31 | 000: WHITE | 4550725164922 | L | 1 |
| 32 | 000: WHITE | 4550725164939 | XL | 5 |
| 39 | 002: LIGHT GREY | 4550725164960 | S | 1 |
| 40 | 002: LIGHT GREY | 4550725164991 | XL | 3 |
| 45 | 009: BLACK | 4550725165028 | S | 1 |
| 46 | 009: BLACK | 4550725165059 | XL | 3 |
| 51 | 014: LIGHT BEIGE | 4550725165080 | S | 3 |
| 52 | 014: LIGHT BEIGE | 4550725165097 | M | 1 |
| 53 | 014: LIGHT BEIGE | 4550725165103 | L | 1 |
| 54 | 014: LIGHT BEIGE | 4550725165110 | XL | 5 |
| 61 | 066: SMOKY BLUE | 4550725165141 | S | 1 |
| 62 | 066: SMOKY BLUE | 4550725165172 | XL | 3 |
| 67 | 069: DARK NAVY | 4550725165202 | S | 1 |
| 68 | 069: DARK NAVY | 4550725165233 | XL | 3 |

**Yang harus dilewati:** baris 12–25 (ASSORT PACK); baris 33–36, 41–42, 47–48, 55–58, 63–64, 69–70 (carton merge); semua baris TOTAL/QTY/GRAND TOTAL. Daftar ini dipakai sebagai test fixture. Daftar ini sudah dicocokkan dengan skrip yang membaca merged range file contoh (16 baris, 36 carton).

## 8. UI, validasi, acceptance criteria

**Layar import**

- Area upload `.xlsx` dengan status proses (membaca → AI → validasi).
- Kartu ringkasan: buyer, style, country, ocf; jumlah baris diimport; jumlah carton; jumlah dilewati.
- Tabel preview 9 kolom (plus JAN). Operator bisa menghapus baris atau mengedit `color`/`size` sebelum simpan.
- Tab "Dilewati" berisi nomor baris Excel dan alasannya.
- Tombol Import aktif hanya jika tidak ada error blocking. Loading dan pesan error jelas bila AI gagal atau timeout (coba ulang satu kali).

**Acceptance criteria**

- [ ] Migrasi tabel ke struktur baru berjalan tanpa kehilangan data lama (atau data lama diarsipkan sesuai keputusan di open questions).
- [ ] File contoh menghasilkan tepat 16 baris dan 36 carton seperti tabel di bagian 7.
- [ ] Tidak ada satu pun baris ASSORT PACK yang masuk database.
- [ ] Tidak ada baris dengan carton merge yang masuk database.
- [ ] Setiap baris yang dilewati muncul di tab Dilewati dengan alasan.
- [ ] `receive_qty_carton = 0` dan `balance = packing_list_qty_carton` untuk semua baris baru.
- [ ] Import bersifat transaksional; gagal di tengah tidak meninggalkan data parsial.
- [ ] Output AI yang tidak sesuai schema ditolak dengan pesan jelas, tidak ada crash.
- [ ] API key AI tidak pernah terlihat di client atau log.
- [ ] Import ulang file yang sama tidak menggandakan data.

**Error handling**

- File bukan xlsx, sheet kosong, header (BUYER/STYLE/DESTINATION/CPO) tidak ditemukan → pesan error spesifik, tidak memanggil AI jika header tidak ada.
- Hasil AI tidak cocok dengan cek total carton dari kode → tampilkan peringatan merah di preview dan blokir tombol Import sampai dicek operator.

Batasan: jangan hardcode API key; jangan import ASSORT atau carton merge; jangan menghapus
data lama tanpa konfirmasi; tampilkan ringkasan perubahan di akhir tiap fase.
```
