<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')
                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h1 class="h4 mb-0 text-gray-800">
                                <i class="fas fa-qrcode text-primary mr-1"></i> {{ $judul }}
                            </h1>
                            <p class="mb-0 text-muted small">
                                Hanya menampilkan data. Tidak ada perubahan yang disimpan.
                            </p>
                        </div>
                        <span class="badge badge-light border py-2 px-3">
                            Jenis kode: <strong class="text-primary">{{ strtoupper($mode) }}</strong>
                        </span>
                    </div>

                    <div class="row">
                        <!-- Pemindai -->
                        <div class="col-lg-5 mb-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header py-2 bg-white">
                                    <h6 class="m-0 font-weight-bold text-primary">Pindai QR</h6>
                                </div>
                                <div class="card-body">
                                    <div class="qr-viewport mb-2">
                                        <video id="scanVideo" playsinline muted class="d-none"></video>
                                        <div id="scanViewfinder" class="qr-viewfinder d-none" aria-hidden="true">
                                            <span class="qr-corner qr-tl"></span>
                                            <span class="qr-corner qr-tr"></span>
                                            <span class="qr-corner qr-bl"></span>
                                            <span class="qr-corner qr-br"></span>
                                            <div class="qr-laser"></div>
                                        </div>
                                        <div id="scanPlaceholder" class="qr-placeholder">
                                            <i class="fas fa-qrcode fa-2x mb-2"></i>
                                            <div class="small">Kamera belum aktif</div>
                                        </div>
                                        <div id="scanCamStatus" class="qr-status"></div>
                                    </div>

                                    <div class="small text-muted text-center mb-2">
                                        {{ $arah }} — arahkan ke dalam kotak.
                                    </div>

                                    <div class="d-flex flex-wrap align-items-center mb-3">
                                        <button type="button" class="btn btn-sm btn-primary mr-2 mb-1" id="btnScanStart">
                                            <i class="fas fa-camera mr-1"></i>Scan dengan Kamera
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-1 d-none" id="btnScanStop">
                                            <i class="fas fa-stop mr-1"></i>Stop
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-dark mb-1 d-none" id="btnScanTorch" title="Senter">
                                            <i class="fas fa-bolt mr-1"></i>Senter
                                        </button>
                                    </div>

                                    <label class="small font-weight-bold">Atau ketik / scan pakai USB scanner</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" id="inputKode" autocomplete="off"
                                            placeholder="{{ $mode === 'pallet' ? 'mis. PLT-0042' : 'mis. B-a-15' }}">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-primary" type="button" id="btnCari">Cari</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hasil -->
                        <div class="col-lg-7 mb-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header py-2 bg-white d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-primary">Hasil</h6>
                                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnBersihkan">
                                        <i class="fas fa-times mr-1"></i>Bersihkan
                                    </button>
                                </div>
                                <div class="card-body" id="hasilBox">
                                    <div class="text-muted small text-center py-5" id="hasilKosong">
                                        Belum ada kode yang dipindai.
                                    </div>
                                    <div id="hasilIsi" class="d-none">
                                        <div id="hasilPeringatan"></div>
                                        <div id="hasilIdentitas"></div>
                                        <div id="hasilRingkas" class="row mb-3"></div>
                                        <h6 class="font-weight-bold text-dark mb-2">Isi Karton</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered table-hover mb-0">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Jancode</th>
                                                        <th>Size</th>
                                                        <th>Color</th>
                                                        <th>Style</th>
                                                        <th>Negara</th>
                                                        <th class="text-right">Karton</th>
                                                        <th class="text-right">Pcs</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="hasilTabel"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            @include('layout.footer')

    <script src="{{ asset('vendor/qr-scanner/jsQR.min.js') }}"></script>
    <script src="{{ asset('vendor/qr-scanner/qr-scanner.js') }}"></script>

    <style>
        .qr-viewport { position: relative; width: 100%; height: 280px; background: #111; border-radius: 10px; overflow: hidden; }
        .qr-viewport video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .qr-placeholder { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #8b93a7;
            background: repeating-linear-gradient(45deg, #171a21, #171a21 12px, #1d212a 12px, #1d212a 24px); }
        .qr-viewfinder { position: absolute; top: 50%; left: 50%; width: 190px; height: 190px; transform: translate(-50%, -50%);
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, .45); border-radius: 10px; pointer-events: none; }
        .qr-corner { position: absolute; width: 28px; height: 28px; border: 3px solid #4e73df; }
        .qr-tl { top: -2px; left: -2px; border-right: 0; border-bottom: 0; border-top-left-radius: 10px; }
        .qr-tr { top: -2px; right: -2px; border-left: 0; border-bottom: 0; border-top-right-radius: 10px; }
        .qr-bl { bottom: -2px; left: -2px; border-right: 0; border-top: 0; border-bottom-left-radius: 10px; }
        .qr-br { bottom: -2px; right: -2px; border-left: 0; border-top: 0; border-bottom-right-radius: 10px; }
        .qr-laser { position: absolute; left: 6px; right: 6px; height: 2px; background: linear-gradient(90deg, transparent, #4e73df, transparent); animation: qr-scan 2.2s linear infinite; }
        @keyframes qr-scan { 0% { top: 8px; opacity: .2; } 50% { opacity: 1; } 100% { top: calc(100% - 10px); opacity: .2; } }
        .qr-viewfinder.qr-hit .qr-corner { border-color: #1cc88a; }
        .qr-status { position: absolute; left: 0; right: 0; bottom: 0; padding: 6px 10px; font-size: 12px; color: #e8ecf5; background: rgba(0, 0, 0, .55); text-align: center; }
        .qr-status.text-danger { background: rgba(180, 30, 30, .75); color: #fff !important; }
        .id-baris { display: flex; justify-content: space-between; padding: .35rem 0; border-bottom: 1px dashed #e3e6f0; }
        .id-baris:last-child { border-bottom: 0; }
        .id-label { color: #858796; font-size: .8rem; }
        .id-nilai { font-weight: 700; }
    </style>

    <script>
    $(function () {
        const MODE = @json($mode);
        const CSRF = $('meta[name="csrf-token"]').attr('content');
        const URL_LOKASI = @json(route('lookup.lokasi', ['code' => '__KODE__']));
        const URL_PALLET = @json(route('lookup.pallet', ['code' => '__KODE__']));

        let scanner = null;

        // ---------- kamera ----------
        function camStatus(msg, isError) {
            const el = document.getElementById('scanCamStatus');
            el.textContent = msg || '';
            el.classList.toggle('text-danger', !!isError);
            el.classList.toggle('text-muted', !isError);
        }

        function startCamera() {
            if (!window.QrScanner) { camStatus('Pustaka pemindai QR tidak termuat.', true); return; }

            stopCamera();

            const video = document.getElementById('scanVideo');
            video.classList.remove('d-none');
            $('#scanPlaceholder').addClass('d-none');
            $('#scanViewfinder').removeClass('d-none qr-hit');
            camStatus('Meminta izin kamera…');

            scanner = new window.QrScanner(video, {
                // Hanya pindai isi kotak bidik: pada stiker baru ada DUA QR
                // berdampingan (lokasi + pallet), jadi user harus bisa memilih.
                scanRegion: function () {
                    const vf = document.getElementById('scanViewfinder');
                    const vp = document.querySelector('.qr-viewport');
                    if (!vf || !vp || vf.classList.contains('d-none')) return null;
                    const a = vf.getBoundingClientRect();
                    const b = vp.getBoundingClientRect();
                    return { x: a.left - b.left, y: a.top - b.top, w: a.width, h: a.height };
                },
                requiredHits: 2,
                onCandidate: function (v, hits, need) {
                    if (hits < need) camStatus('Membaca ' + v + '… (' + hits + '/' + need + ')');
                },
                onResult: function (v) {
                    $('#scanViewfinder').addClass('qr-hit');
                    camStatus('Terbaca: ' + v);
                    if (navigator.vibrate) { try { navigator.vibrate(60); } catch (e) {} }
                    stopCamera();
                    $('#inputKode').val(v);
                    cari(v);
                },
                onStatus: function (m) { camStatus(m); },
                onError: function (m) { camStatus(m, true); $('#btnScanStart').removeClass('d-none'); $('#btnScanStop').addClass('d-none'); $('#scanViewfinder').addClass('d-none'); }
            });

            scanner.start().then(function () {
                const track = scanner && scanner.stream ? scanner.stream.getVideoTracks()[0] : null;
                let caps = {};
                try { caps = track && track.getCapabilities ? (track.getCapabilities() || {}) : {}; } catch (e) { caps = {}; }
                if (caps.torch) {
                    let on = false;
                    $('#btnScanTorch').removeClass('d-none').off('click').on('click', function () {
                        on = !on;
                        track.applyConstraints({ advanced: [{ torch: on }] }).catch(function () {});
                        $(this).toggleClass('btn-dark', on).toggleClass('btn-outline-dark', !on);
                    });
                }
            }).catch(function () {});

            $('#btnScanStart').addClass('d-none');
            $('#btnScanStop').removeClass('d-none');
        }

        function stopCamera() {
            if (scanner) { scanner.stop(); scanner = null; }
            const v = document.getElementById('scanVideo');
            if (v) { v.classList.add('d-none'); v.srcObject = null; }
            $('#scanPlaceholder').removeClass('d-none');
            $('#scanViewfinder').addClass('d-none').removeClass('qr-hit');
            $('#btnScanStart').removeClass('d-none');
            $('#btnScanStop').addClass('d-none');
            $('#btnScanTorch').addClass('d-none').removeClass('btn-dark').addClass('btn-outline-dark');
        }

        $('#btnScanStart').on('click', startCamera);
        $('#btnScanStop').on('click', stopCamera);

        // ---------- pencarian ----------
        function cari(kode) {
            kode = (kode || '').trim();
            if (! kode) return;

            const base = MODE === 'pallet' ? URL_PALLET : URL_LOKASI;
            const url = base.replace('__KODE__', encodeURIComponent(kode));

            $.getJSON(url)
                .done(function (res) { render(res); })
                .fail(function (xhr) {
                    const j = (xhr && xhr.responseJSON) || {};
                    tampilError(j.message || 'Kode tidak bisa dibaca.');
                });
        }

        function tampilError(msg) {
            $('#hasilKosong').addClass('d-none');
            $('#hasilIsi').removeClass('d-none');
            $('#btnBersihkan').removeClass('d-none');
            $('#hasilPeringatan').html('<div class="alert alert-danger py-2 small mb-3">' + msg + '</div>');
            $('#hasilIdentitas, #hasilRingkas, #hasilTabel').html('');
        }

        function baris(label, nilai) {
            return '<div class="id-baris"><span class="id-label">' + label + '</span><span class="id-nilai">' + nilai + '</span></div>';
        }

        function kartu(warna, judul, isi) {
            return '<div class="col-md-4 mb-2"><div class="card border-left-' + warna + ' shadow-sm py-2">' +
                   '<div class="card-body py-2"><div class="text-xs font-weight-bold text-' + warna +
                   ' text-uppercase mb-1">' + judul + '</div><div class="h6 mb-0 font-weight-bold text-gray-800">' + isi + '</div>' +
                   '</div></div></div>';
        }

        function render(res) {
            $('#hasilKosong').addClass('d-none');
            $('#hasilIsi').removeClass('d-none');
            $('#btnBersihkan').removeClass('d-none');

            // peringatan
            let warn = '';
            (res.peringatan || []).forEach(function (p) {
                warn += '<div class="alert alert-warning py-2 small mb-3"><i class="fas fa-exclamation-triangle mr-1"></i>' + p + '</div>';
            });
            $('#hasilPeringatan').html(warn);

            // identitas
            let id = '';
            if (res.jenis === 'lokasi') {
                id += '<h6 class="font-weight-bold text-dark mb-2">Lokasi <code>' + res.lokasi.kode + '</code></h6>';
                id += baris('Row / Line / Posisi', res.lokasi.row + ' / ' + res.lokasi.line + ' / ' + res.lokasi.pos);
                id += baris('Kapasitas', res.lokasi.kapasitas + ' karton');
                id += baris('Mode negara', res.lokasi.mode_negara);
                id += baris('Pallet di lokasi ini', res.pallet
                    ? '<span class="text-primary">' + res.pallet.kode + '</span> <span class="badge badge-info ml-1">' + res.pallet.label + '</span>'
                    : '<span class="text-muted">belum ditempati pallet</span>');
                if (res.pallet && res.pallet.sejak) {
                    id += baris('Pallet sejak', res.pallet.sejak);
                }
            } else {
                id += '<h6 class="font-weight-bold text-dark mb-2">Pallet <code>' + res.pallet.kode + '</code>' +
                      ' <span class="badge badge-info ml-1">' + res.pallet.label + '</span></h6>';
                id += baris('Kapasitas pallet', res.pallet.kapasitas + ' karton');
                id += baris('Lokasi sekarang', res.lokasi
                    ? '<span class="text-primary">' + res.lokasi.kode + '</span>'
                    : '<span class="text-muted">belum ditempatkan</span>');
                if (res.pallet.sejak) {
                    id += baris('Di lokasi sejak', res.pallet.sejak);
                }
            }
            $('#hasilIdentitas').html('<div class="mb-3">' + id + '</div>');

            // ringkasan
            const terisi = res.total_karton;
            const kapasitas = res.jenis === 'lokasi'
                ? (res.pallet ? res.pallet.kapasitas : res.lokasi.kapasitas)
                : res.pallet.kapasitas;
            const sisa = Math.max(0, kapasitas - terisi);

            $('#hasilRingkas').html(
                kartu('primary', 'Total karton', terisi) +
                kartu('info', 'Kapasitas', kapasitas) +
                kartu(sisa > 0 ? 'success' : 'danger', 'Sisa slot', sisa)
            );

            // tabel isi
            let rows = '';
            (res.isi || []).forEach(function (it) {
                rows += '<tr><td><code>' + it.jancode + '</code></td><td>' + it.size + '</td><td>' + it.color +
                        '</td><td>' + it.style + '</td><td>' + it.negara +
                        '</td><td class="text-right font-weight-bold">' + it.qty_karton +
                        '</td><td class="text-right">' + it.qty_pcs.toLocaleString() + '</td></tr>';
            });
            if (! rows) {
                rows = '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada karton.</td></tr>';
            }
            $('#hasilTabel').html(rows);
        }

        $('#btnCari').on('click', function () { cari($('#inputKode').val()); });
        $('#inputKode').on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); cari($(this).val()); }
        });

        $('#btnBersihkan').on('click', function () {
            $('#inputKode').val('').focus();
            $('#hasilIsi').addClass('d-none');
            $('#hasilKosong').removeClass('d-none');
            $(this).addClass('d-none');
            camStatus('');
        });

        $('#inputKode').focus();
    });
    </script>
</body>
</html>
