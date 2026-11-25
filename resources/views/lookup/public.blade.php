<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1a4d8f">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $judul }} — CT-PAT JAN Code Scanner</title>
    <style>
        /* Mobile & tablet first: satu kolom, target sentuh besar. */
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        :root {
            --biru: #1a4d8f;
            --biru-muda: #2f6fd0;
            --hijau: #1cc88a;
            --merah: #c0392b;
            --kuning: #b7791f;
            --abu: #6b7280;
            --garis: #e3e6ef;
            --latar: #f2f4f8;
        }

        html, body { margin: 0; padding: 0; }
        .d-none { display: none !important; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--latar);
            color: #1f2430;
            font-size: 16px;
            line-height: 1.45;
            padding-bottom: 24px;
        }

        header.bar {
            background: var(--biru);
            color: #fff;
            padding: 14px 16px calc(14px + env(safe-area-inset-bottom, 0px));
            padding-top: calc(14px + env(safe-area-inset-top, 0px));
            position: sticky; top: 0; z-index: 20;
        }
        header.bar h1 { margin: 0; font-size: 1.15rem; font-weight: 700; letter-spacing: .2px; }
        header.bar p { margin: 2px 0 0; font-size: .8rem; opacity: .85; }

        .tab {
            display: flex; gap: 8px; padding: 10px 16px 0;
            max-width: 760px; margin: 0 auto;
        }
        .tab a {
            flex: 1; text-align: center; text-decoration: none;
            padding: 12px 10px; border-radius: 10px 10px 0 0;
            background: #dfe4ee; color: var(--biru); font-weight: 700; font-size: .95rem;
            min-height: 46px; display: flex; align-items: center; justify-content: center;
        }
        .tab a.aktif { background: #fff; box-shadow: 0 -2px 6px rgba(0,0,0,.06); }

        main { max-width: 760px; margin: 0 auto; padding: 0 16px; }

        .kartu {
            background: #fff; border-radius: 12px; padding: 16px;
            box-shadow: 0 1px 4px rgba(20,30,60,.08); margin-top: 14px;
        }
        .kartu:first-of-type { border-radius: 0 0 12px 12px; }

        label { display: block; font-size: .82rem; font-weight: 700; color: var(--abu); margin-bottom: 6px; }

        input[type=text] {
            width: 100%; padding: 14px 14px; font-size: 1.15rem;
            border: 2px solid var(--garis); border-radius: 10px; min-height: 52px;
            letter-spacing: .5px;
        }
        input[type=text]:focus { outline: none; border-color: var(--biru-muda); }

        .tombol {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 14px 16px; min-height: 52px;
            border: 0; border-radius: 10px; font-size: 1rem; font-weight: 700;
            background: var(--biru); color: #fff; cursor: pointer;
        }
        .tombol.biru-muda { background: var(--biru-muda); }
        .tombol.putih { background: #fff; color: var(--biru); border: 2px solid var(--biru); }
        .tombol.abu { background: #e5e8f0; color: #374151; }
        .tombol:active { opacity: .82; }
        .tombol[disabled] { opacity: .5; cursor: default; }

        .baris-tombol { display: flex; gap: 10px; margin-top: 10px; }
        .baris-tombol .tombol { flex: 1; }

        .viewport {
            position: relative; width: 100%; aspect-ratio: 4 / 3; background: #111;
            border-radius: 12px; overflow: hidden; margin-top: 12px;
        }
        .viewport video { width: 100%; height: 100%; object-fit: cover; display: block; }

        .placeholder {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; color: #8b93a7; text-align: center;
            background: repeating-linear-gradient(45deg, #171a21, #171a21 12px, #1d212a 12px, #1d212a 24px);
            font-size: .85rem; padding: 12px;
        }

        .bidik {
            position: absolute; top: 50%; left: 50%; width: 58%; aspect-ratio: 1 / 1;
            max-width: 240px; transform: translate(-50%, -50%);
            box-shadow: 0 0 0 9999px rgba(0,0,0,.45); border-radius: 12px; pointer-events: none;
        }
        .sudut { position: absolute; width: 15%; min-width: 26px; aspect-ratio: 1; border: 3px solid #6c9dff; }
        .s-tl { top: -2px; left: -2px; border-right: 0; border-bottom: 0; border-top-left-radius: 12px; }
        .s-tr { top: -2px; right: -2px; border-left: 0; border-bottom: 0; border-top-right-radius: 12px; }
        .s-bl { bottom: -2px; left: -2px; border-right: 0; border-top: 0; border-bottom-left-radius: 12px; }
        .s-br { bottom: -2px; right: -2px; border-left: 0; border-top: 0; border-bottom-right-radius: 12px; }
        .laser {
            position: absolute; left: 6px; right: 6px; height: 2px;
            background: linear-gradient(90deg, transparent, #6c9dff, transparent);
            animation: jalan 2.2s linear infinite;
        }
        @keyframes jalan { 0% { top: 8px; opacity: .2; } 50% { opacity: 1; } 100% { top: calc(100% - 10px); opacity: .2; } }
        .bidik.kena .sudut { border-color: var(--hijau); }

        .status-kamera {
            position: absolute; left: 0; right: 0; bottom: 0; padding: 8px 10px;
            font-size: .8rem; text-align: center; color: #e8ecf5; background: rgba(0,0,0,.55);
        }
        .status-kamera.galat { background: rgba(192,57,43,.85); color: #fff; }

        .pesan { border-radius: 10px; padding: 12px 14px; font-size: .9rem; margin-top: 12px; }
        .pesan.galat { background: #fdecea; color: #922b21; border: 1px solid #f5c6c0; }
        .pesan.ingat { background: #fff8e6; color: #7a5a12; border: 1px solid #f2e0b0; }
        .pesan.oke { background: #e8f8f1; color: #14795a; border: 1px solid #b9e8d4; }

        .identitas h2 { margin: 0 0 4px; font-size: 1.5rem; letter-spacing: .5px; }
        .identitas .baris { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px dashed var(--garis); font-size: .92rem; }
        .identitas .baris:last-child { border-bottom: 0; }
        .identitas .kunci { color: var(--abu); }
        .identitas .nilai { font-weight: 700; text-align: right; }

        .chip-baris { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 12px; }
        .chip { background: #f7f9fc; border: 1px solid var(--garis); border-radius: 10px; padding: 10px; text-align: center; }
        .chip .angka { font-size: 1.35rem; font-weight: 800; }
        .chip .nama { font-size: .72rem; color: var(--abu); text-transform: uppercase; letter-spacing: .5px; }
        .chip.biru .angka { color: var(--biru); }
        .chip.hijau .angka { color: var(--hijau); }
        .chip.merah .angka { color: var(--merah); }

        .label-status {
            display: inline-block; font-size: .72rem; font-weight: 700; padding: 3px 8px;
            border-radius: 999px; background: #e8eefc; color: var(--biru); vertical-align: middle;
        }

        .tabel-bungkus { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 10px; }
        table { border-collapse: collapse; width: 100%; min-width: 520px; font-size: .88rem; }
        th, td { border-bottom: 1px solid var(--garis); padding: 9px 8px; text-align: left; white-space: nowrap; }
        th { background: #f7f9fc; font-size: .74rem; text-transform: uppercase; letter-spacing: .4px; color: var(--abu); }
        td.kanan, th.kanan { text-align: right; }
        tbody tr:last-child td { border-bottom: 0; }

        .kosong { text-align: center; color: var(--abu); padding: 18px 8px; font-size: .9rem; }

        footer {
            max-width: 760px; margin: 18px auto 0; padding: 0 16px;
            font-size: .75rem; color: var(--abu); text-align: center;
        }
    </style>
</head>
<body>

<header class="bar">
    <h1><span style="opacity:.8">🔍</span> {{ $judul }}</h1>
    <p>{{ $arah }}</p>
</header>

<div class="tab">
    <a href="{{ route('lookup.lokasi.page') }}" class="{{ $mode === 'lokasi' ? 'aktif' : '' }}">Lokasi</a>
    <a href="{{ route('lookup.pallet.page') }}" class="{{ $mode === 'pallet' ? 'aktif' : '' }}">Pallet</a>
</div>

<main>
    <div class="kartu">
        <label for="inputKode">Kode (scan dengan kamera, USB scanner, atau ketik)</label>
        <input type="text" id="inputKode" inputmode="text" autocomplete="off" autocapitalize="characters"
               spellcheck="false" placeholder="{{ $mode === 'pallet' ? 'mis. PLT-0042' : 'mis. B-a-15' }}">

        <div class="baris-tombol">
            <button type="button" class="tombol" id="btnCari">Cari</button>
            <button type="button" class="tombol abu" id="btnBersih">Bersihkan</button>
        </div>

        <div class="baris-tombol">
            <button type="button" class="tombol biru-muda" id="btnKamera">Buka Kamera</button>
            <button type="button" class="tombol putih d-none" id="btnStop">Stop Kamera</button>
        </div>

        <div class="viewport d-none" id="viewport">
            <video id="video" playsinline muted></video>
            <div class="bidik d-none" id="bidik">
                <span class="sudut s-tl"></span>
                <span class="sudut s-tr"></span>
                <span class="sudut s-bl"></span>
                <span class="sudut s-br"></span>
                <div class="laser"></div>
            </div>
            <div class="placeholder" id="placeholder">
                <div style="font-size:1.6rem">📷</div>
                <div>Arahkan kamera ke QR</div>
            </div>
            <div class="status-kamera" id="statusKamera"></div>
        </div>
    </div>

    <div id="pesanBox"></div>

    <div class="kartu d-none" id="hasilKartu">
        <div class="identitas" id="identitas"></div>
        <div class="chip-baris" id="chips"></div>
        <div id="peringatanBox"></div>
        <h3 style="font-size:.95rem;margin:16px 0 0">Isi Karton</h3>
        <div style="font-size:.75rem;color:#6b7280;margin-top:2px">Geser ke samping untuk melihat semua kolom.</div>
        <div class="tabel-bungkus">
            <table>
                <thead>
                    <tr>
                        <th>Jancode</th>
                        <th>Size</th>
                        <th>Color</th>
                        <th>Style</th>
                        <th>Negara</th>
                        <th class="kanan">Karton</th>
                        <th class="kanan">Pcs</th>
                    </tr>
                </thead>
                <tbody id="tabelIsi"></tbody>
            </table>
        </div>
    </div>
</main>

<footer>
    Halaman publik — hanya menampilkan data, tidak ada perubahan yang disimpan.
</footer>

<script src="{{ asset('vendor/qr-scanner/jsQR.min.js') }}"></script>
<script src="{{ asset('vendor/qr-scanner/qr-scanner.js') }}"></script>
<script>
(function () {
    'use strict';

    var MODE = @json($mode);
    var URL_LOKASI = @json(route('lookup.lokasi', ['code' => '__KODE__']));
    var URL_PALLET = @json(route('lookup.pallet', ['code' => '__KODE__']));

    var $ = function (id) { return document.getElementById(id); };
    var scanner = null;

    // ---------- pesan ----------
    function pesan(teks, jenis) {
        var box = $('pesanBox');
        if (!teks) { box.innerHTML = ''; return; }
        box.innerHTML = '<div class="pesan ' + (jenis || 'ingat') + '">' + teks + '</div>';
    }

    // ---------- kamera ----------
    function statusKamera(teks, galat) {
        var el = $('statusKamera');
        el.textContent = teks || '';
        el.className = 'status-kamera' + (galat ? ' galat' : '');
    }

    function bukaKamera() {
        if (!window.QrScanner) { pesan('Pustaka pemindai QR tidak termuat.', 'galat'); return; }

        tutupKamera();

        $('viewport').classList.remove('d-none');
        $('placeholder').classList.add('d-none');
        $('bidik').classList.remove('d-none', 'kena');
        statusKamera('Meminta izin kamera…');

        scanner = new window.QrScanner($('video'), {
            // Stiker memuat dua QR berdampingan, jadi hanya QR di dalam kotak
            // bidik yang diterima.
            scanRegion: function () {
                var b = $('bidik');
                var v = $('viewport');
                if (!b || b.classList.contains('d-none')) return null;
                var rb = b.getBoundingClientRect();
                var rv = v.getBoundingClientRect();
                return { x: rb.left - rv.left, y: rb.top - rv.top, w: rb.width, h: rb.height };
            },
            requiredHits: 2,
            onCandidate: function (v, hits, need) {
                if (hits < need) statusKamera('Membaca ' + v + '… (' + hits + '/' + need + ')');
            },
            onResult: function (v) {
                $('bidik').classList.add('kena');
                statusKamera('Terbaca: ' + v);
                if (navigator.vibrate) { try { navigator.vibrate(60); } catch (e) {} }
                tutupKamera();
                $('inputKode').value = v;
                cari(v);
            },
            onStatus: function (m) { statusKamera(m); },
            onError: function (m) {
                statusKamera(m, true);
                $('btnKamera').classList.remove('d-none');
                $('btnStop').classList.add('d-none');
                $('bidik').classList.add('d-none');
                $('placeholder').classList.remove('d-none');
            }
        });

        scanner.start().catch(function () { /* pesan sudah tampil */ });

        $('btnKamera').classList.add('d-none');
        $('btnStop').classList.remove('d-none');
    }

    function tutupKamera() {
        if (scanner) { scanner.stop(); scanner = null; }
        var v = $('video');
        if (v) { v.srcObject = null; }
        $('viewport').classList.add('d-none');
        $('placeholder').classList.remove('d-none');
        $('bidik').classList.add('d-none');
        $('bidik').classList.remove('kena');
        $('btnKamera').classList.remove('d-none');
        $('btnStop').classList.add('d-none');
    }

    // ---------- pencarian ----------
    function cari(kode) {
        kode = (kode || '').trim();
        if (!kode) { pesan('Isi atau scan kode dulu.', 'ingat'); return; }

        var base = (MODE === 'pallet') ? URL_PALLET : URL_LOKASI;
        var url = base.replace('__KODE__', encodeURIComponent(kode));

        pesan('');
        $('hasilKartu').classList.add('d-none');

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, data: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.data.success) {
                    pesan(res.data.message || 'Kode tidak bisa dibaca.', 'galat');
                    return;
                }
                render(res.data);
            })
            .catch(function () { pesan('Gagal menghubungi server.', 'galat'); });
    }

    function baris(kunci, nilai) {
        return '<div class="baris"><span class="kunci">' + kunci + '</span><span class="nilai">' + nilai + '</span></div>';
    }

    function render(d) {
        var html = '';

        if (d.jenis === 'lokasi') {
            html += '<h2>' + d.lokasi.kode + '</h2>';
            html += '<div style="margin-bottom:8px"><span class="label-status">LOKASI</span></div>';
            html += baris('Row / Line / Posisi', d.lokasi.row + ' / ' + d.lokasi.line + ' / ' + d.lokasi.pos);
            html += baris('Kapasitas', d.lokasi.kapasitas + ' karton');
            html += baris('Mode negara', d.lokasi.mode_negara);
            html += baris('Status', d.lokasi.aktif ? 'Aktif' : 'NONAKTIF');
            html += baris('Pallet di lokasi ini', d.pallet
                ? d.pallet.kode + ' <span class="label-status">' + d.pallet.label + '</span>'
                : '<span style="color:#6b7280">belum ada pallet</span>');
            if (d.pallet && d.pallet.sejak) html += baris('Pallet sejak', d.pallet.sejak);
        } else {
            html += '<h2>' + d.pallet.kode + '</h2>';
            html += '<div style="margin-bottom:8px"><span class="label-status">PALLET</span> <span class="label-status">' + d.pallet.label + '</span></div>';
            html += baris('Kapasitas', d.pallet.kapasitas + ' karton');
            html += baris('Lokasi sekarang', d.lokasi
                ? d.lokasi.kode + ' <span style="color:#6b7280">(row ' + d.lokasi.row + ' line ' + d.lokasi.line + ' pos ' + d.lokasi.pos + ')</span>'
                : '<span style="color:#6b7280">belum ditempatkan</span>');
            if (d.pallet.sejak) html += baris('Di lokasi sejak', d.pallet.sejak);
        }
        $('identitas').innerHTML = html;

        // ringkasan
        var kapasitas = (d.jenis === 'lokasi')
            ? (d.pallet ? d.pallet.kapasitas : d.lokasi.kapasitas)
            : d.pallet.kapasitas;
        var terisi = d.total_karton;
        var sisa = Math.max(0, kapasitas - terisi);

        $('chips').innerHTML =
            '<div class="chip biru"><div class="angka">' + terisi + '</div><div class="nama">Total karton</div></div>' +
            '<div class="chip"><div class="angka">' + kapasitas + '</div><div class="nama">Kapasitas</div></div>' +
            '<div class="chip ' + (sisa > 0 ? 'hijau' : 'merah') + '"><div class="angka">' + sisa + '</div><div class="nama">Sisa slot</div></div>';

        // peringatan
        var w = '';
        (d.peringatan || []).forEach(function (p) { w += '<div class="pesan ingat">' + p + '</div>'; });
        $('peringatanBox').innerHTML = w;

        // tabel
        var rows = '';
        (d.isi || []).forEach(function (it) {
            rows += '<tr>' +
                '<td>' + it.jancode + '</td>' +
                '<td>' + it.size + '</td>' +
                '<td>' + it.color + '</td>' +
                '<td>' + it.style + '</td>' +
                '<td>' + it.negara + '</td>' +
                '<td class="kanan"><strong>' + it.qty_karton + '</strong></td>' +
                '<td class="kanan">' + it.qty_pcs.toLocaleString('id-ID') + '</td>' +
                '</tr>';
        });
        $('tabelIsi').innerHTML = rows || '<tr><td colspan="7" class="kosong">Tidak ada karton.</td></tr>';

        $('hasilKartu').classList.remove('d-none');
        $('hasilKartu').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ---------- pasang aksi ----------
    $('btnCari').addEventListener('click', function () { cari($('inputKode').value); });

    $('inputKode').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); cari(this.value); }
    });

    $('btnKamera').addEventListener('click', bukaKamera);
    $('btnStop').addEventListener('click', tutupKamera);

    $('btnBersih').addEventListener('click', function () {
        tutupKamera();
        $('inputKode').value = '';
        $('inputKode').focus();
        pesan('');
        $('hasilKartu').classList.add('d-none');
    });

    // Siapkan untuk USB scanner: fokus otomatis ke kolom kode.
    $('inputKode').focus();
})();
</script>
</body>
</html>
