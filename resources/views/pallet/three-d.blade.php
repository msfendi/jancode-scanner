<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<style>
    #threeWrap {
        position: relative;
        width: 100%;
        height: calc(100vh - 210px);
        min-height: 560px;
        background: radial-gradient(1200px 600px at 50% 0%, #1e2338 0%, #10131f 60%, #0a0c14 100%);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .28);
    }
    #threeCanvas { display: block; width: 100%; height: 100%; }

    .hud {
        position: absolute;
        z-index: 5;
        background: rgba(16, 20, 34, .82);
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 10px;
        color: #e7ebf5;
        backdrop-filter: blur(6px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, .35);
    }
    .hud-title {
        font-size: 10px;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: #8f9bb5;
        margin-bottom: 4px;
    }
    #hudStats { top: 12px; left: 12px; padding: 10px 14px; min-width: 190px; }
    #hudStats .stat-row { display: flex; justify-content: space-between; font-size: 12px; line-height: 1.7; }
    #hudStats .stat-row b { font-weight: 700; }
    #hudStats .big { font-size: 17px; font-weight: 800; color: #4dd4ac; }

    #hudControls { top: 12px; right: 12px; padding: 10px; width: 244px; }
    #hudControls .btn { font-size: 11.5px; font-weight: 600; }
    #hudControls .btn-block { margin-bottom: 6px; }
    .hud-group-label { font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: #8f9bb5; margin: 8px 0 4px; }

    #hudLegend { bottom: 12px; left: 12px; padding: 10px 12px; max-width: 260px; max-height: 168px; overflow: auto; }
    #hudLegend .lg-item { display: flex; align-items: center; font-size: 11.5px; margin-bottom: 3px; }
    #hudLegend .sw { width: 12px; height: 12px; border-radius: 3px; margin-right: 7px; border: 1px solid rgba(255,255,255,.25); flex: 0 0 auto; }
    #hudLegend .lg-item span.lbl { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    #hudInfo { bottom: 12px; right: 12px; padding: 12px 14px; width: 300px; max-height: 300px; overflow: auto; display: none; }
    #hudInfo .code { font-size: 16px; font-weight: 800; }
    #hudInfo table { width: 100%; font-size: 11.5px; color: #d6dcea; }
    #hudInfo table td { padding: 2px 0; border-bottom: 1px dashed rgba(255,255,255,.08); }
    #hudInfo .close { color: #cfd6e6; cursor: pointer; opacity: .8; }

    #hudHint { bottom: 12px; left: 50%; transform: translateX(-50%); padding: 6px 12px; font-size: 11px; color: #aab4cc; }

    #threeLoading {
        position: absolute; inset: 0; z-index: 20;
        display: flex; align-items: center; justify-content: center; flex-direction: column;
        background: #10131f; color: #cfd6e6;
    }
    #threeLoading .spinner-border { width: 2.4rem; height: 2.4rem; }

    #truckBadge {
        position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
        z-index: 6; padding: 6px 14px; font-size: 12px; font-weight: 600;
        background: rgba(16, 20, 34, .85); color: #ffd166; border-radius: 20px;
        border: 1px solid rgba(255, 209, 102, .35); display: none;
    }
    .live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #6c757d; margin-right: 5px; }
    .live-dot.on { background: #4dd4ac; box-shadow: 0 0 8px #4dd4ac; }
</style>

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
                            <h1 class="h4 mb-1 text-gray-800 font-weight-bold">
                                Visualisasi 3D Layout Pallet
                            </h1>
                            <p class="text-muted small mb-0">
                                Gudang Row B–I (kolom a–d) &bull; simulasi handlift angkut karton masuk / keluar
                            </p>
                        </div>
                        <div>
                            <a href="{{ route('pallet.index') }}" class="btn btn-sm btn-outline-secondary shadow-sm">
                                <i class="fas fa-th fa-sm mr-1"></i> Board 2D
                            </a>
                            <a href="{{ route('scanner.index') }}" class="btn btn-sm btn-success shadow-sm">
                                <i class="fas fa-barcode fa-sm mr-1"></i> Buka Scanner
                            </a>
                        </div>
                    </div>

                    @include('layout.workflow-steps', ['current' => 'pallet'])
                    @include('layout.session-banner', ['currentSession' => $currentSession])

                    <div id="threeWrap">
                        <canvas id="threeCanvas"></canvas>

                        <div class="hud" id="hudStats">
                            <div class="hud-title">Status Gudang</div>
                            <div class="big"><span id="stCartons">0</span> <span style="font-size:11px;color:#8f9bb5">KARTON</span></div>
                            <div class="stat-row"><span>Pallet terisi</span><b id="stOccupied">0</b></div>
                            <div class="stat-row"><span>Pallet kosong</span><b id="stVacant">0</b></div>
                            <div class="stat-row"><span>Total posisi</span><b id="stPositions">0</b></div>
                            <div class="stat-row"><span>Kapasitas</span><b id="stCapacity">0</b></div>
                            <div class="stat-row" style="margin-top:6px;">
                                <span><span class="live-dot" id="liveDot"></span><span id="liveText">Live: mati</span></span>
                            </div>
                        </div>

                        <div class="hud" id="hudControls">
                            <div class="hud-title">Kontrol Simulasi</div>
                            <button type="button" class="btn btn-warning btn-block btn-sm" id="btnDemoOut">
                                <i class="fas fa-dolly mr-1"></i> Demo Carton Outbound
                            </button>
                            <button type="button" class="btn btn-success btn-block btn-sm" id="btnDemoIn">
                                <i class="fas fa-boxes mr-1"></i> Demo Karton Masuk
                            </button>
                            <button type="button" class="btn btn-outline-info btn-block btn-sm" id="btnDemoAuto">
                                <i class="fas fa-play mr-1"></i> Putar Simulasi Otomatis
                            </button>

                            <div class="hud-group-label">Tampilan</div>
                            <div class="btn-group btn-group-sm w-100 mb-2" role="group">
                                <button type="button" class="btn btn-outline-light" id="btnViewIso">Isometrik</button>
                                <button type="button" class="btn btn-outline-light" id="btnViewTop">Atas</button>
                                <button type="button" class="btn btn-outline-light" id="btnViewFront">Depan</button>
                            </div>
                            <button type="button" class="btn btn-outline-light btn-block btn-sm" id="btnAutoRotate">
                                <i class="fas fa-sync-alt mr-1"></i> Putar kamera: mati
                            </button>
                            <button type="button" class="btn btn-outline-light btn-block btn-sm" id="btnLabels">
                                <i class="fas fa-tag mr-1"></i> Label: tampil
                            </button>
                            <button type="button" class="btn btn-outline-light btn-block btn-sm" id="btnFocus">
                                <i class="fas fa-crosshairs mr-1"></i> Fokus pallet terisi
                            </button>

                            <div class="hud-group-label">Data</div>
                            <button type="button" class="btn btn-outline-success btn-block btn-sm" id="btnLive">
                                <i class="fas fa-broadcast-tower mr-1"></i> Auto-sync: mati
                            </button>
                            <button type="button" class="btn btn-outline-light btn-block btn-sm" id="btnSync">
                                <i class="fas fa-download mr-1"></i> Samakan dengan data
                            </button>
                            <div class="small text-muted mt-2" style="font-size:10.5px;" id="syncTs">—</div>
                        </div>

                        <div class="hud" id="hudLegend">
                            <div class="hud-title">Style / OCF terisi</div>
                            <div id="legendBody"><span class="text-muted small">Belum ada pallet terisi.</span></div>
                        </div>

                        <div class="hud" id="hudInfo">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="hud-title mb-0">Detail Pallet</div>
                                    <div class="code" id="infoCode">-</div>
                                </div>
                                <span class="close" id="infoClose"><i class="fas fa-times"></i></span>
                            </div>
                            <div class="small text-muted mb-2" id="infoMeta">-</div>
                            <table id="infoTable">
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="hud" id="hudHint">
                            Drag = putar &nbsp;|&nbsp; Scroll = zoom &nbsp;|&nbsp; Shift+Drag = geser &nbsp;|&nbsp; Klik pallet = detail
                        </div>

                        <div id="truckBadge"></div>

                        <div id="threeLoading">
                            <div class="spinner-border text-success" role="status"></div>
                            <div class="mt-3 small">Menyiapkan gudang 3D…</div>
                        </div>
                    </div>

                    <div class="alert alert-light border shadow-sm my-4 small mb-0">
                        <div class="d-flex">
                            <i class="fas fa-info-circle fa-lg text-primary mr-3"></i>
                            <div>
                                Visualisasi ini <strong>read-only</strong> (tidak mengubah data). Tombol <em>Demo</em> hanya
                                memainkan animasi handlift untuk ilustrasi. Jika perubahan nyata terjadi (scan / penempatan /
                                karton keluar) dan <strong>Auto-sync</strong> menyala, animasi in/out akan berjalan otomatis.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('layout.footer')

            <script src="{{ asset('vendor/three/three.min.js') }}"></script>
            <script src="{{ asset('js/pallet-3d.js') }}"></script>
            <script>
                Pallet3D.boot({
                    dataUrl: "{{ route('pallet.threeDData') }}"
                });
            </script>
        </div>
    </div>
</body>
</html>
