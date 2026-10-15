<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<style>
    /* Container Drag-to-Scroll Horizontal Board */
    .pallet-board-wrapper {
        position: relative;
        background: #eaedf2;
        border-radius: 10px;
        padding: 8px;
    }
    .pallet-board-scroll {
        display: flex;
        flex-direction: row;
        overflow-x: auto;
        gap: 16px;
        padding: 8px 6px 20px 6px;
        cursor: grab;
        user-select: none;
        scroll-behavior: smooth;
        scrollbar-width: thin;
        transform-origin: top left;
        transition: transform 0.15s ease;
    }
    .pallet-board-scroll.active {
        cursor: grabbing;
        scroll-behavior: auto;
    }
    .pallet-board-scroll::-webkit-scrollbar {
        height: 10px;
    }
    .pallet-board-scroll::-webkit-scrollbar-thumb {
        background-color: #9ba1b8;
        border-radius: 6px;
    }

    /* Card Tiap Row B-I: Vertikal Column Layout */
    .pallet-row-card {
        flex: 0 0 290px;
        width: 290px;
        min-width: 290px;
        border: 1px solid #d1d3e2;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 0.15rem 1.25rem 0 rgba(58, 59, 69, 0.12);
        border-top: 5px solid #4e73df;
        display: flex;
        flex-direction: column;
        transition: opacity 0.2s ease, transform 0.2s ease;
    }

    .pallet-row-card.locked-card {
        border-top: 5px solid #e74a3b;
    }

    /* Highlight matching cards in search */
    .pallet-row-card.search-match {
        border: 3px solid #f6c23e;
        box-shadow: 0 0 12px rgba(246,194,62,0.6);
    }
    .pallet-row-card.search-dim {
        opacity: 0.25;
        transform: scale(0.98);
    }

    /* Subrow Header Bar: a | b or c | d */
    .subrow-vert-header {
        display: flex;
        align-items: center;
        background: #eaecf4;
        border-radius: 5px;
        padding: 4px 6px;
        margin-bottom: 6px;
        font-weight: 800;
        font-size: 13px;
        text-align: center;
    }
    .subrow-header-pos {
        width: 32px;
        font-size: 10px;
        color: #6e707e;
        text-transform: uppercase;
    }
    .subrow-header-col {
        flex: 1;
        color: #4e73df;
    }

    /* Tiap baris vertikal 1..14 / 1..16 */
    .pallet-vert-row {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 4px;
    }
    .pallet-pos-tag {
        width: 32px;
        font-size: 11px;
        font-weight: 700;
        color: #6e707e;
        text-align: center;
        background: #f1f3f9;
        border: 1px solid #e3e6f0;
        border-radius: 4px;
        padding: 4px 0;
        user-select: none;
    }

    /* Kotak Pallet */
    .pallet-box {
        flex: 1;
        height: 44px;
        padding: 2px 4px;
        font-size: 11px;
        border-radius: 5px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
        border: 1px solid rgba(0,0,0,0.12);
        line-height: 1.2;
    }
    .pallet-box:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 4px 10px rgba(0,0,0,0.22);
        z-index: 3;
    }
    .pallet-vacant {
        background-color: #1cc88a;
        color: #fff;
    }
    .pallet-occupied {
        background-color: #e74a3b;
        color: #fff;
    }
    .font-mono {
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }

    /* ========================= FULLSCREEN MODE ========================= */
    body.fullscreen-mode #wrapper { display: none; }
    body.fullscreen-mode #page-top { padding: 0; }

    #fullscreenOverlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: #1a1d2e;
        z-index: 9999;
        flex-direction: column;
    }
    #fullscreenOverlay.active {
        display: flex;
    }
    #fullscreenTopBar {
        background: #2c3152;
        color: #fff;
        padding: 10px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
        border-bottom: 2px solid #4e73df;
    }
    #fullscreenTopBar .fs-title {
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    #fullscreenTopBar .fs-controls {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    #fullscreenBoard {
        flex: 1;
        overflow: auto;
        padding: 16px;
    }
    #fullscreenBoard .pallet-board-scroll {
        min-height: calc(100vh - 80px);
    }
    .zoom-label {
        color: #adb5d9;
        font-size: 13px;
        min-width: 48px;
        text-align: center;
    }

    /* Search Results Panel */
    #searchResultsPanel {
        display: none;
        border: 2px solid #f6c23e;
        border-radius: 8px;
        background: #fffdf0;
        padding: 14px 18px;
        margin-bottom: 16px;
    }
    #searchResultsPanel.active { display: block; }
    .search-result-tag {
        display: inline-flex;
        align-items: center;
        background: #4e73df;
        color: #fff;
        border-radius: 20px;
        padding: 3px 12px;
        margin: 2px 4px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .search-result-tag:hover { background: #2e59d9; }
</style>

<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">Manage Pallet Location & Placement</h1>
                            <p class="text-muted small mb-0">Total 480 Alamat Pallet (Row B–I) &bull; Kapasitas Maks 42 Karton/Pallet &bull; Aturan Kompatibilitas 1 Country & 1 Style/OCF per Row</p>
                        </div>
                        <div>
                            <a href="{{ route('jancode.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                                <i class="fas fa-barcode fa-sm text-white-50 mr-1"></i> Master Jancode
                            </a>
                            <button class="btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#quickPlotModal">
                                <i class="fas fa-plus fa-sm text-white-50 mr-1"></i> Plot Karton Baru
                            </button>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Alamat Pallet</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalPositions }} Posisi</div>
                                            <div class="small text-muted">8 Row (B–I) &times; 60 Posisi</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-th fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Pallet Terisi (Merah)</div>
                                            <div class="h5 mb-0 font-weight-bold text-danger">{{ $occupiedCount }} Pallet</div>
                                            <div class="small text-muted">{{ $totalPositions > 0 ? round(($occupiedCount / $totalPositions) * 100, 1) : 0 }}% Terpakai</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-pallet fa-2x text-danger opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Pallet Kosong (Hijau)</div>
                                            <div class="h5 mb-0 font-weight-bold text-success">{{ $vacantCount }} Pallet</div>
                                            <div class="small text-muted">Siap ditempatkan</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Karton Ditempatkan</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($totalCartons) }} Karton</div>
                                            <div class="small text-muted">Dari total kapasitas {{ number_format($totalCapacity) }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-boxes fa-2x text-info opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Panel -->
                    <div id="searchResultsPanel">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-weight-bold text-warning"><i class="fas fa-search-location mr-2"></i>Hasil Pencarian Pallet:</span>
                            <button class="btn btn-sm btn-outline-warning" id="btnClearSearch"><i class="fas fa-times mr-1"></i>Bersihkan</button>
                        </div>
                        <div id="searchResultTags" class="mb-1"></div>
                        <small class="text-muted" id="searchResultInfo"></small>
                    </div>

                    <!-- Row Filter & Drag Nav Bar -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <!-- Row 1: Filter & Nav -->
                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center flex-wrap mb-2 mb-md-0">
                                    <span class="font-weight-bold text-primary mr-3"><i class="fas fa-filter mr-1"></i> Filter Row:</span>
                                    <div class="btn-group btn-group-sm flex-wrap mr-3" role="group">
                                        <a href="{{ route('pallet.index', ['row' => 'all']) }}" class="btn {{ $selectedRow === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">Semua (B–I)</a>
                                        @foreach(['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] as $rLetter)
                                            <a href="{{ route('pallet.index', ['row' => $rLetter]) }}" class="btn {{ $selectedRow === $rLetter ? 'btn-primary' : 'btn-outline-primary' }}">Row {{ $rLetter }}</a>
                                        @endforeach
                                    </div>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary" id="btnScrollLeft" title="Geser ke Kiri">
                                            <i class="fas fa-chevron-left"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="btnScrollRight" title="Geser ke Kanan">
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge badge-success px-2 py-1 mr-2"><i class="fas fa-circle mr-1"></i> Hijau: Kosong</span>
                                    <span class="badge badge-danger px-2 py-1 mr-2"><i class="fas fa-circle mr-1"></i> Merah: Terisi</span>
                                    <button type="button" class="btn btn-sm btn-dark ml-1" id="btnFullscreen" title="Fullscreen View">
                                        <i class="fas fa-expand mr-1"></i> Fullscreen
                                    </button>
                                </div>
                            </div>
                            <!-- Row 2: Search Bar -->
                            <div class="border-top pt-2 mt-1">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <span class="font-weight-bold text-secondary mr-2"><i class="fas fa-search mr-1"></i> Cari Lokasi Pallet:</span>
                                    <div class="input-group input-group-sm mr-2" style="max-width: 200px;">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-globe"></i></span></div>
                                        <input type="text" class="form-control" id="searchCountry" placeholder="Country (mis. JAPAN)">
                                    </div>
                                    <div class="input-group input-group-sm mr-2" style="max-width: 180px;">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tag"></i></span></div>
                                        <input type="text" class="form-control" id="searchOcf" placeholder="OCF (mis. 1234)">
                                    </div>
                                    <div class="input-group input-group-sm mr-2" style="max-width: 200px;">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tshirt"></i></span></div>
                                        <input type="text" class="form-control" id="searchStyle" placeholder="Style (mis. AB1234)">
                                    </div>
                                    <button type="button" class="btn btn-sm btn-warning font-weight-bold" id="btnSearch">
                                        <i class="fas fa-search mr-1"></i> Cari
                                    </button>
                                    <small class="text-muted"><i class="fas fa-arrows-alt-h mr-1"></i> Drag untuk menjelajah</small>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-2">
                            <!-- Horizontal Draggable Canvas Container -->
                            <div class="pallet-board-wrapper">
                                <div class="pallet-board-scroll" id="palletBoardScroll">
                                    @foreach(['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] as $rowLetter)
                                        @if($selectedRow === 'all' || $selectedRow === $rowLetter)
                                            @php
                                                $summary = $rowSummaries[$rowLetter] ?? null;
                                                $isRowLocked = $summary && $summary['is_locked'];
                                            @endphp
                                            <div class="pallet-row-card {{ $isRowLocked ? 'locked-card' : '' }}" data-row="{{ $rowLetter }}">
                                                <!-- Header Row Card -->
                                                <div class="card-header py-2 bg-light border-bottom">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="m-0 font-weight-bold text-dark">
                                                            <i class="fas fa-warehouse text-primary mr-1"></i> ROW {{ $rowLetter }}
                                                        </h6>
                                                        @if($isRowLocked)
                                                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-lock mr-1"></i> Terkunci</span>
                                                        @else
                                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-lock-open mr-1"></i> Terbuka</span>
                                                        @endif
                                                    </div>

                                                    @if($isRowLocked)
                                                        <div class="mt-2 pt-2 border-top small">
                                                            <div class="text-truncate font-weight-bold text-dark" title="{{ $summary['country'] }}">
                                                                <i class="fas fa-globe text-secondary mr-1"></i> {{ $summary['country'] }}
                                                            </div>
                                                            <div class="text-truncate text-muted" title="{{ $summary['style'] }} | OCF: {{ $summary['ocf'] }}">
                                                                <i class="fas fa-tshirt text-secondary mr-1"></i> {{ $summary['style'] }}
                                                                <span class="badge badge-secondary ml-1">OCF: {{ $summary['ocf'] ?: '-' }}</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                                <span class="font-weight-bold text-danger">
                                                                    <i class="fas fa-boxes mr-1"></i> {{ $summary['total_cartons'] }} ctn
                                                                </span>
                                                                <span class="text-dark small">
                                                                    ({{ number_format($summary['total_pcs']) }} pcs)
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <!-- Breakdown Size per Color Accordion Button -->
                                                        @if(!empty($summary['color_breakdown']))
                                                            <div class="mt-2">
                                                                <button class="btn btn-xs btn-outline-primary btn-block text-left d-flex justify-content-between align-items-center py-1" 
                                                                        type="button" data-toggle="collapse" data-target="#breakdown-{{ $rowLetter }}">
                                                                    <span><i class="fas fa-tags mr-1"></i> <strong>Breakdown Size & Color</strong></span>
                                                                    <i class="fas fa-chevron-down small"></i>
                                                                </button>
                                                                <div class="collapse mt-2" id="breakdown-{{ $rowLetter }}">
                                                                    <div class="p-2 bg-white rounded border small" style="max-height: 220px; overflow-y: auto;">
                                                                        @foreach($summary['color_breakdown'] as $cName => $cData)
                                                                            <div class="mb-2 pb-1 border-bottom">
                                                                                <div class="font-weight-bold text-dark d-flex justify-content-between">
                                                                                    <span class="text-truncate mr-1" title="{{ $cName }}">{{ $cName }}</span>
                                                                                    <span class="badge badge-info">{{ $cData['total_cartons'] }} ctn</span>
                                                                                </div>
                                                                                <div class="d-flex flex-wrap mt-1">
                                                                                    @foreach($cData['sizes'] as $sName => $sCartons)
                                                                                        <span class="badge badge-light border text-dark mr-1 mb-1 font-mono">
                                                                                            {{ $sName }}: <strong>{{ $sCartons }}</strong> ctn
                                                                                        </span>
                                                                                    @endforeach
                                                                                </div>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @else
                                                        <div class="mt-1 small text-muted font-italic">
                                                            Belum terisi (Bebas Country/Style/OCF)
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- Body Pallet Vertikal Grid -->
                                                <div class="card-body p-2" style="background: #fafbfe;">
                                                    <!-- BAGIAN 1: Subrow a & b (1 s/d 14) -->
                                                    <div class="text-center font-weight-bold small text-primary mb-1">
                                                        AREA DEPAN (a & b)
                                                    </div>
                                                    <div class="subrow-vert-header">
                                                        <div class="subrow-header-pos">Pos</div>
                                                        <div class="subrow-header-col">a</div>
                                                        <div class="subrow-header-col">b</div>
                                                    </div>

                                                    @for($p = 1; $p <= 14; $p++)
                                                        @php
                                                            $addrA = $groupedRows[$rowLetter]['a'][$p] ?? null;
                                                            $isOccA = $addrA && $addrA->assignment && $addrA->assignment->total_carton > 0;
                                                            $cartonsA = $isOccA ? $addrA->assignment->total_carton : 0;

                                                            $addrB = $groupedRows[$rowLetter]['b'][$p] ?? null;
                                                            $isOccB = $addrB && $addrB->assignment && $addrB->assignment->total_carton > 0;
                                                            $cartonsB = $isOccB ? $addrB->assignment->total_carton : 0;
                                                        @endphp
                                                        <div class="pallet-vert-row">
                                                            <span class="pallet-pos-tag">{{ $p }}</span>
                                                            @if($addrA)
                                                                <div class="pallet-box {{ $isOccA ? 'pallet-occupied' : 'pallet-vacant' }}"
                                                                     onclick="openPalletModal({{ $addrA->id }})"
                                                                     title="{{ $addrA->code }}: {{ $isOccA ? $cartonsA . '/42 Karton (' . $addrA->assignment->style . ' - ' . $addrA->assignment->country . ')' : 'Kosong (Maks 42)' }}">
                                                                    <span class="font-weight-bold">{{ $addrA->code }}</span>
                                                                    <span class="small font-weight-bold">{{ $isOccA ? $cartonsA . ' ctn' : '0' }}</span>
                                                                </div>
                                                            @endif
                                                            @if($addrB)
                                                                <div class="pallet-box {{ $isOccB ? 'pallet-occupied' : 'pallet-vacant' }}"
                                                                     onclick="openPalletModal({{ $addrB->id }})"
                                                                     title="{{ $addrB->code }}: {{ $isOccB ? $cartonsB . '/42 Karton (' . $addrB->assignment->style . ' - ' . $addrB->assignment->country . ')' : 'Kosong (Maks 42)' }}">
                                                                    <span class="font-weight-bold">{{ $addrB->code }}</span>
                                                                    <span class="small font-weight-bold">{{ $isOccB ? $cartonsB . ' ctn' : '0' }}</span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endfor

                                                    <!-- DIVIDER ANTARA DEPAN & BELAKANG -->
                                                    <hr class="my-3" style="border-top: 2px dashed #b7b9cc;">

                                                    <!-- BAGIAN 2: Subrow c & d (1 s/d 16) -->
                                                    <div class="text-center font-weight-bold small text-secondary mb-1">
                                                        AREA BELAKANG (c & d)
                                                    </div>
                                                    <div class="subrow-vert-header">
                                                        <div class="subrow-header-pos">Pos</div>
                                                        <div class="subrow-header-col text-secondary">c</div>
                                                        <div class="subrow-header-col text-secondary">d</div>
                                                    </div>

                                                    @for($p = 1; $p <= 16; $p++)
                                                        @php
                                                            $addrC = $groupedRows[$rowLetter]['c'][$p] ?? null;
                                                            $isOccC = $addrC && $addrC->assignment && $addrC->assignment->total_carton > 0;
                                                            $cartonsC = $isOccC ? $addrC->assignment->total_carton : 0;

                                                            $addrD = $groupedRows[$rowLetter]['d'][$p] ?? null;
                                                            $isOccD = $addrD && $addrD->assignment && $addrD->assignment->total_carton > 0;
                                                            $cartonsD = $isOccD ? $addrD->assignment->total_carton : 0;
                                                        @endphp
                                                        <div class="pallet-vert-row">
                                                            <span class="pallet-pos-tag">{{ $p }}</span>
                                                            @if($addrC)
                                                                <div class="pallet-box {{ $isOccC ? 'pallet-occupied' : 'pallet-vacant' }}"
                                                                     onclick="openPalletModal({{ $addrC->id }})"
                                                                     title="{{ $addrC->code }}: {{ $isOccC ? $cartonsC . '/42 Karton (' . $addrC->assignment->style . ' - ' . $addrC->assignment->country . ')' : 'Kosong (Maks 42)' }}">
                                                                    <span class="font-weight-bold">{{ $addrC->code }}</span>
                                                                    <span class="small font-weight-bold">{{ $isOccC ? $cartonsC . ' ctn' : '0' }}</span>
                                                                </div>
                                                            @endif
                                                            @if($addrD)
                                                                <div class="pallet-box {{ $isOccD ? 'pallet-occupied' : 'pallet-vacant' }}"
                                                                     onclick="openPalletModal({{ $addrD->id }})"
                                                                     title="{{ $addrD->code }}: {{ $isOccD ? $cartonsD . '/42 Karton (' . $addrD->assignment->style . ' - ' . $addrD->assignment->country . ')' : 'Kosong (Maks 42)' }}">
                                                                    <span class="font-weight-bold">{{ $addrD->code }}</span>
                                                                    <span class="small font-weight-bold">{{ $isOccD ? $cartonsD . ' ctn' : '0' }}</span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endfor
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @include('layout.footer')
        </div>
    </div>

    <!-- Modal Detail Pallet & Manajemen Item -->
    <div class="modal fade" id="palletDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-pallet mr-2"></i> Detail Pallet: <span id="modalPalletCode">-</span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <!-- Status Kapasitas & Kunci Grup -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="card border-left-info shadow-sm py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Status Kapasitas</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <span id="modalTotalCarton">0</span> / 42 Karton
                                    </div>
                                    <div class="small text-muted">Sisa Slot: <strong id="modalAvailableSpace">42</strong></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-left-primary shadow-sm py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Terkunci Style</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="modalLockedStyle">-</div>
                                    <div class="small text-muted">Aturan BR-09 (1 Style/Pallet)</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-left-success shadow-sm py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Terkunci Country</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="modalLockedCountry">-</div>
                                    <div class="small text-muted">Aturan BR-09 (1 Country/Pallet)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Item di Pallet ini -->
                    <h6 class="font-weight-bold text-dark mb-2">Daftar Item Karton di Pallet Ini:</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Color</th>
                                    <th>Size</th>
                                    <th>JAN Code</th>
                                    <th class="text-right">Karton di Pallet</th>
                                    <th class="text-right">Total Master</th>
                                    <th class="text-right">Receive Scan</th>
                                    <th class="text-right">Balance</th>
                                    <th class="text-right">Total Pcs</th>
                                    <th width="90" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="modalItemsTableBody">
                                <tr><td colspan="9" class="text-center text-muted py-3">Pallet masih kosong.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Form Tambah Karton ke Pallet Ini -->
                    <div class="card bg-light border-0 shadow-sm p-3 mb-3">
                        <h6 class="font-weight-bold text-primary mb-2">
                            <i class="fas fa-plus-circle mr-1"></i> Penempatan Karton Hasil Scan ke Pallet Ini:
                        </h6>
                        <form id="formAddCartonToPallet">
                            <input type="hidden" id="addCartonPalletId" name="pallet_address_id">
                            <div class="form-row align-items-end">
                                <div class="col-md-7 mb-2">
                                    <label class="small font-weight-bold mb-1">Pilih Item Master Jancode (Hasil Scan):</label>
                                    <select class="form-control form-control-sm" id="selectAddJancode" name="jancode_id" required>
                                        <option value="">-- Pilih Item Hasil Scan --</option>
                                        @foreach($availableJancodes as $j)
                                            <option value="{{ $j->id }}" 
                                                    data-style="{{ $j->style }}" 
                                                    data-country="{{ $j->country ?: $j->destination }}"
                                                    data-unplaced="{{ $j->unplaced_carton }}"
                                                    data-receive="{{ $j->receive_qty }}"
                                                    data-total="{{ $j->total_pl_carton }}"
                                                    data-balance="{{ $j->balance }}">
                                                {{ $j->style }} | {{ $j->country ?: $j->destination }} | {{ $j->color }} | Sz: {{ $j->size }} [Scan: {{ $j->receive_qty }} ctn, Siap: {{ $j->unplaced_carton }} ctn]
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small font-weight-bold mb-1">Qty Karton Ditempatkan:</label>
                                    <input type="number" class="form-control form-control-sm bg-white" id="inputAddQtyCarton" name="qty_carton" readonly placeholder="Otomatis dari scan">
                                    <small class="text-muted d-block" id="infoAddQty">Otomatis dari hasil scan</small>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <button type="submit" class="btn btn-sm btn-primary btn-block">
                                        <i class="fas fa-check mr-1"></i> Tempatkan
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Tombol Kosongkan Pallet -->
                    <div class="text-right">
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnClearPallet" style="display: none;">
                            <i class="fas fa-trash-alt mr-1"></i> Kosongkan Seluruh Pallet Ini
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pindahkan Karton (Move Carton) -->
    <div class="modal fade" id="moveCartonModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark font-weight-bold">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-exchange-alt mr-2"></i> Pindah Karton ke Pallet Lain
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formMoveCarton">
                    <div class="modal-body p-4">
                        <input type="hidden" id="moveSourceDetailId" name="source_detail_id">

                        <div class="alert alert-info py-2 small mb-3">
                            <strong>Item yang Dipindahkan:</strong><br>
                            <span id="moveItemDesc">-</span><br>
                            Karton Tersedia di Pallet Asal: <strong id="moveMaxQty">0</strong> karton.
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Jumlah Karton yang Dipindah:</label>
                            <input type="number" class="form-control" id="moveQtyInput" name="qty_carton" min="1" required>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Pilih Pallet Tujuan:</label>
                            <select class="form-control" id="moveTargetPalletSelect" name="target_pallet_address_id" required>
                                <option value="">-- Pilih Pallet Tujuan --</option>
                                @foreach($addresses as $addr)
                                    @php
                                        $tAssign = $addr->assignment;
                                        $tCartons = $tAssign ? $tAssign->total_carton : 0;
                                        $tSisa = max(0, 42 - $tCartons);
                                        $statusStr = $tCartons == 0 ? 'Kosong (42 slot)' : "Terisi {$tCartons}/42 ({$tAssign->style} - {$tAssign->country})";
                                    @endphp
                                    @if($tSisa > 0)
                                        <option value="{{ $addr->id }}" 
                                                data-style="{{ $tAssign ? $tAssign->style : '' }}"
                                                data-country="{{ $tAssign ? $tAssign->country : '' }}"
                                                data-sisa="{{ $tSisa }}">
                                            {{ $addr->code }} &mdash; {{ $statusStr }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            <small class="text-muted">Pallet tujuan harus kosong atau memiliki Style & Country yang sama (BR-09).</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning font-weight-bold">
                            <i class="fas fa-check mr-1"></i> Proses Perpindahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Quick Plot Baru -->
    <div class="modal fade" id="quickPlotModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-plus-circle mr-2"></i> Plot Karton Baru ke Pallet
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formQuickPlot">
                    <div class="modal-body p-4">
                        <div class="form-group">
                            <label class="font-weight-bold">Pilih Item Master Jancode:</label>
                            <select class="form-control" name="jancode_id" id="quickJancodeSelect" required>
                                <option value="">-- Pilih Item Hasil Scan --</option>
                                @foreach($availableJancodes as $j)
                                    <option value="{{ $j->id }}"
                                            data-style="{{ $j->style }}"
                                            data-country="{{ $j->country ?: $j->destination }}"
                                            data-unplaced="{{ $j->unplaced_carton }}"
                                            data-receive="{{ $j->receive_qty }}">
                                        {{ $j->style }} | {{ $j->country ?: $j->destination }} | {{ $j->color }} | Sz: {{ $j->size }} [Scan: {{ $j->receive_qty }} ctn, Siap: {{ $j->unplaced_carton }} ctn]
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Pilih Alamat Pallet Tujuan:</label>
                            <select class="form-control" name="pallet_address_id" id="quickPalletSelect" required>
                                <option value="">-- Pilih Pallet --</option>
                                @foreach($addresses as $addr)
                                    @php
                                        $tAssign = $addr->assignment;
                                        $tCartons = $tAssign ? $tAssign->total_carton : 0;
                                        $tSisa = max(0, 42 - $tCartons);
                                    @endphp
                                    @if($tSisa > 0)
                                        <option value="{{ $addr->id }}" data-sisa="{{ $tSisa }}">
                                            {{ $addr->code }} (Sisa Slot: {{ $tSisa }}/42) {{ $tAssign ? '- ' . $tAssign->style . ' (' . $tAssign->country . ')' : '- Kosong' }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Qty Karton yang Ditempatkan:</label>
                            <input type="number" class="form-control bg-white" name="qty_carton" id="quickQtyInput" readonly placeholder="Otomatis dari hasil scan">
                            <small class="text-muted d-block" id="quickQtyInfo">Otomatis dari hasil scan dan sisa kapasitas pallet.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary font-weight-bold">
                            <i class="fas fa-check mr-1"></i> Tempatkan Karton
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== FULLSCREEN OVERLAY ==================== -->
    <div id="fullscreenOverlay">
        <div id="fullscreenTopBar">
            <span class="fs-title"><i class="fas fa-warehouse mr-2"></i> Visualisasi Pallet — Fullscreen Mode</span>
            <div class="fs-controls">
                <!-- Search in fullscreen -->
                <input type="text" id="fsSearchCountry" class="form-control form-control-sm" placeholder="Country" style="width:130px; background:#3a3f5c; color:#fff; border-color:#5a6080;">
                <input type="text" id="fsSearchOcf" class="form-control form-control-sm" placeholder="OCF" style="width:110px; background:#3a3f5c; color:#fff; border-color:#5a6080;">
                <input type="text" id="fsSearchStyle" class="form-control form-control-sm" placeholder="Style" style="width:140px; background:#3a3f5c; color:#fff; border-color:#5a6080;">
                <button class="btn btn-sm btn-warning" id="fsBtnSearch"><i class="fas fa-search"></i></button>
                <div class="border-left border-secondary mx-2" style="height:24px;"></div>
                <!-- Zoom Controls -->
                <button class="btn btn-sm btn-outline-light" id="fsBtnZoomOut" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
                <span class="zoom-label" id="fsZoomLabel">100%</span>
                <button class="btn btn-sm btn-outline-light" id="fsBtnZoomIn" title="Zoom In"><i class="fas fa-search-plus"></i></button>
                <button class="btn btn-sm btn-outline-light" id="fsBtnZoomReset" title="Reset Zoom"><i class="fas fa-compress-arrows-alt"></i></button>
                <div class="border-left border-secondary mx-2" style="height:24px;"></div>
                <button class="btn btn-sm btn-outline-danger" id="fsBtnClose"><i class="fas fa-times mr-1"></i>Tutup</button>
            </div>
        </div>
        <div id="fullscreenBoard">
            <!-- Board will be mirrored/cloned here dynamically -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentOpenPalletId = null;

        // Buka Modal Detail Pallet
        function openPalletModal(addressId) {
            currentOpenPalletId = addressId;
            $('#addCartonPalletId').val(addressId);

            $.get("{{ url('pallet/detail') }}/" + addressId, function(res) {
                if (!res.success) return;

                const addr = res.address;
                const assign = res.assignment;

                $('#modalPalletCode').text(addr.code);
                $('#modalTotalCarton').text(assign ? assign.total_carton : 0);
                $('#modalAvailableSpace').text(res.available_space);
                $('#modalLockedStyle').text(assign ? assign.style : '- (Bebas)');
                $('#modalLockedCountry').text(assign ? assign.country : '- (Bebas)');

                if (assign && assign.total_carton > 0) {
                    $('#btnClearPallet').show();
                } else {
                    $('#btnClearPallet').hide();
                }

                // Render tabel item
                let html = '';
                if (res.items.length === 0) {
                    html = '<tr><td colspan="9" class="text-center text-muted py-3">Pallet masih kosong.</td></tr>';
                } else {
                    res.items.forEach(function(it) {
                        const balanceDisplay = (it.balance > 0)
                            ? `<span class="text-warning font-weight-bold">${it.balance} ctn</span>`
                            : `<span class="text-success font-weight-bold">0 (Lengkap)</span>`;

                        html += `<tr>
                            <td class="font-weight-bold">${it.color}</td>
                            <td><span class="badge badge-primary px-2">${it.size}</span></td>
                            <td class="font-mono">${it.jan_code}</td>
                            <td class="text-right font-weight-bold text-danger">${it.qty_carton} ctn</td>
                            <td class="text-right text-muted">${it.total_qty || '-'} ctn</td>
                            <td class="text-right font-weight-bold text-success">${it.receive_qty || 0} ctn</td>
                            <td class="text-right">${balanceDisplay}</td>
                            <td class="text-right font-weight-bold">${it.total_pcs.toLocaleString()} pcs</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-warning btn-xs mr-1" onclick="openMoveModal(${it.detail_id}, '${it.color}', '${it.size}', ${it.qty_carton}, '${addr.code}')" title="Pindah karton ke pallet lain">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                                <button type="button" class="btn btn-danger btn-xs" onclick="removePalletDetail(${it.detail_id})" title="Hapus dari pallet">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                    });
                }
                $('#modalItemsTableBody').html(html);

                // Reset pilihan form tambah di dalam modal
                $('#selectAddJancode').val('').trigger('change');
                $('#inputAddQtyCarton').val('');
                $('#infoAddQty').text('Pilih item hasil scan');

                $('#palletDetailModal').modal('show');
            });
        }

        // Hitung otomatis Qty Karton saat item dipilih di modal detail pallet
        $('#selectAddJancode').on('change', function() {
            const opt = $(this).find(':selected');
            const unplaced = parseInt(opt.data('unplaced')) || 0;
            const availableSpace = parseInt($('#modalAvailableSpace').text()) || 0;

            if (unplaced > 0) {
                const autoQty = Math.min(unplaced, availableSpace);
                $('#inputAddQtyCarton').val(autoQty);
                $('#infoAddQty').html(`Siap ditempatkan: <strong>${unplaced} ctn</strong> (Slot muat: <strong class="text-primary">${autoQty} ctn</strong>)`);
            } else {
                $('#inputAddQtyCarton').val('');
                $('#infoAddQty').text('Pilih item hasil scan');
            }
        });

        // Hitung otomatis Qty Karton pada Quick Plot Modal
        function updateQuickPlotAutoQty() {
            const jOpt = $('#quickJancodeSelect').find(':selected');
            const pOpt = $('#quickPalletSelect').find(':selected');
            const unplaced = parseInt(jOpt.data('unplaced')) || 0;
            const sisaSlot = parseInt(pOpt.data('sisa')) || 42;

            if (unplaced > 0 && pOpt.val()) {
                const autoQty = Math.min(unplaced, sisaSlot);
                $('#quickQtyInput').val(autoQty);
                $('#quickQtyInfo').html(`Karton hasil scan siap: <strong>${unplaced} ctn</strong>, slot muat: <strong class="text-primary">${autoQty} ctn</strong>.`);
            } else if (unplaced > 0) {
                $('#quickQtyInput').val(unplaced);
                $('#quickQtyInfo').html(`Karton hasil scan siap: <strong>${unplaced} ctn</strong>. Silakan pilih pallet tujuan.`);
            } else {
                $('#quickQtyInput').val('');
                $('#quickQtyInfo').text('Otomatis dari akumulasi scan dan sisa kapasitas pallet.');
            }
        }
        $('#quickJancodeSelect, #quickPalletSelect').on('change', updateQuickPlotAutoQty);

        // Submit Plot Karton ke Pallet Tertentu
        $('#formAddCartonToPallet').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            $.ajax({
                url: "{{ route('pallet.store') }}",
                type: "POST",
                data: form.serialize(),
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(res) {
                    if (res.success) {
                        Swal.fire('Berhasil!', res.message, 'success').then(() => {
                            location.reload();
                        });
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Gagal menempatkan karton.';
                    Swal.fire('Validasi Penempatan Gagal', msg, 'error');
                }
            });
        });

        // Submit Quick Plot
        $('#formQuickPlot').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            $.ajax({
                url: "{{ route('pallet.store') }}",
                type: "POST",
                data: form.serialize(),
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(res) {
                    if (res.success) {
                        Swal.fire('Berhasil!', res.message, 'success').then(() => {
                            location.reload();
                        });
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Gagal menempatkan karton.';
                    Swal.fire('Validasi Penempatan Gagal', msg, 'error');
                }
            });
        });

        // Buka Modal Pindah Karton
        function openMoveModal(detailId, color, size, currentQty, sourcePalletCode) {
            $('#moveSourceDetailId').val(detailId);
            $('#moveItemDesc').text(`Pallet Asal: ${sourcePalletCode} | Warna: ${color} | Size: ${size}`);
            $('#moveMaxQty').text(currentQty);
            $('#moveQtyInput').val(currentQty).attr('max', currentQty);
            $('#moveCartonModal').modal('show');
        }

        // Submit Pindah Karton
        $('#formMoveCarton').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            $.ajax({
                url: "{{ route('pallet.move') }}",
                type: "POST",
                data: form.serialize(),
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(res) {
                    if (res.success) {
                        $('#moveCartonModal').modal('hide');
                        Swal.fire('Perpindahan Berhasil!', res.message, 'success').then(() => {
                            location.reload();
                        });
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Gagal memindahkan karton.';
                    Swal.fire('Perpindahan Ditolak', msg, 'error');
                }
            });
        });

        // Hapus Satu Item dari Pallet
        function removePalletDetail(detailId) {
            Swal.fire({
                title: 'Keluarkan Item dari Pallet?',
                text: 'Karton akan dikembalikan ke sisa kuota master dan slot pallet menjadi kosong kembali.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Keluarkan',
                cancelButtonText: 'Batal'
            }).then((res) => {
                if (res.isConfirmed) {
                    $.ajax({
                        url: "{{ url('pallet/detail') }}/" + detailId,
                        type: "DELETE",
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        success: function(resp) {
                            if (resp.success) {
                                Swal.fire('Berhasil', resp.message, 'success').then(() => {
                                    location.reload();
                                });
                            }
                        }
                    });
                }
            });
        }

        // Kosongkan Seluruh Pallet
        $('#btnClearPallet').click(function() {
            if (!currentOpenPalletId) return;

            Swal.fire({
                title: 'Kosongkan Seluruh Pallet?',
                text: 'Semua karton pada pallet ini akan dikeluarkan dan status pallet menjadi hijau (kosong).',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Kosongkan',
                cancelButtonText: 'Batal'
            }).then((res) => {
                if (res.isConfirmed) {
                    $.ajax({
                        url: "{{ url('pallet/clear') }}/" + currentOpenPalletId,
                        type: "DELETE",
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        success: function(resp) {
                            if (resp.success) {
                                Swal.fire('Berhasil', resp.message, 'success').then(() => {
                                    location.reload();
                                });
                            }
                        }
                    });
                }
            });
        });

        // ==========================================
        // --- Drag-to-Scroll & Navigation Buttons ---
        // ==========================================
        const boardScroll = document.getElementById('palletBoardScroll');
        let isDown = false;
        let startX;
        let scrollLeftPos;

        function setupDragScroll(el) {
            if (!el) return;
            let _isDown = false, _startX, _scrollLeft;
            el.addEventListener('mousedown', (e) => {
                if (e.target.closest('.pallet-box') || e.target.closest('button') || e.target.closest('a')) return;
                _isDown = true;
                el.classList.add('active');
                _startX = e.pageX - el.offsetLeft;
                _scrollLeft = el.scrollLeft;
            });
            el.addEventListener('mouseleave', () => { _isDown = false; el.classList.remove('active'); });
            el.addEventListener('mouseup', () => { _isDown = false; el.classList.remove('active'); });
            el.addEventListener('mousemove', (e) => {
                if (!_isDown) return;
                e.preventDefault();
                const x = e.pageX - el.offsetLeft;
                el.scrollLeft = _scrollLeft - (x - _startX) * 1.6;
            });
        }

        setupDragScroll(boardScroll);

        if (boardScroll) {
            $('#btnScrollLeft').click(function () { boardScroll.scrollBy({ left: -450, behavior: 'smooth' }); });
            $('#btnScrollRight').click(function () { boardScroll.scrollBy({ left: 450, behavior: 'smooth' }); });
        }

        // ==========================================
        // --- Pallet Search Feature ---
        // ==========================================
        // Build search index from DOM data attributes on pallet boxes
        // Each pallet box has title with style/country info; we also embed data on row cards
        @php
            $palletRowMetaList = collect($rowSummaries ?? [])->map(function($s, $row) {
                return [
                    'row'       => $row,
                    'country'   => strtolower($s['country'] ?? ''),
                    'style'     => strtolower($s['style'] ?? ''),
                    'ocf'       => strtolower($s['ocf'] ?? ''),
                    'is_locked' => !empty($s['is_locked']),
                ];
            })->values()->all();
        @endphp
        const palletRowMeta = @json($palletRowMetaList);

        function runPalletSearch(country, ocf, style) {
            const qCountry = (country || '').trim().toLowerCase();
            const qOcf = (ocf || '').trim().toLowerCase();
            const qStyle = (style || '').trim().toLowerCase();

            if (!qCountry && !qOcf && !qStyle) {
                clearPalletSearch();
                return;
            }

            let matched = [];
            palletRowMeta.forEach(function(meta) {
                let ok = true;
                if (qCountry && !meta.country.includes(qCountry)) ok = false;
                if (qOcf && !meta.ocf.includes(qOcf)) ok = false;
                if (qStyle && !meta.style.includes(qStyle)) ok = false;
                if (ok && meta.is_locked) matched.push(meta.row);
            });

            // Highlight/dim row cards
            document.querySelectorAll('.pallet-row-card').forEach(function(card) {
                const row = card.getAttribute('data-row');
                if (!row) return;
                card.classList.remove('search-match', 'search-dim');
                if (matched.includes(row)) {
                    card.classList.add('search-match');
                } else {
                    card.classList.add('search-dim');
                }
            });

            // Show results panel
            const panel = document.getElementById('searchResultsPanel');
            const tagsEl = document.getElementById('searchResultTags');
            const infoEl = document.getElementById('searchResultInfo');
            panel.classList.add('active');

            if (matched.length === 0) {
                tagsEl.innerHTML = '<span class="text-muted">Tidak ada pallet yang cocok.</span>';
                infoEl.textContent = 'Coba kata kunci lain.';
            } else {
                let tagHtml = matched.map(function(row) {
                    const meta = palletRowMeta.find(m => m.row === row);
                    return `<span class="search-result-tag" onclick="scrollToRow('${row}')" title="Scroll ke Row ${row}">
                        <i class="fas fa-map-marker-alt mr-1"></i> ROW ${row}
                        <small class="ml-1 opacity-75">${meta ? meta.style + (meta.ocf ? ' / OCF:' + meta.ocf.toUpperCase() : '') : ''}</small>
                    </span>`;
                }).join('');
                tagsEl.innerHTML = tagHtml;
                infoEl.textContent = `Ditemukan ${matched.length} Row pallet yang cocok. Klik tag untuk scroll ke lokasi.`;
            }

            // Also highlight in fullscreen if open
            syncFullscreenHighlight(matched);
        }

        function clearPalletSearch() {
            document.querySelectorAll('.pallet-row-card').forEach(function(card) {
                card.classList.remove('search-match', 'search-dim');
            });
            document.getElementById('searchResultsPanel').classList.remove('active');
            document.getElementById('searchResultTags').innerHTML = '';
        }

        function scrollToRow(rowLetter) {
            const card = document.querySelector(`.pallet-row-card[data-row="${rowLetter}"]`);
            if (card && boardScroll) {
                const offset = card.offsetLeft - 20;
                boardScroll.scrollTo({ left: offset, behavior: 'smooth' });
            }
        }

        $('#btnSearch').click(function() {
            runPalletSearch($('#searchCountry').val(), $('#searchOcf').val(), $('#searchStyle').val());
        });
        $('#searchCountry, #searchOcf, #searchStyle').on('keypress', function(e) {
            if (e.which === 13) $('#btnSearch').click();
        });
        $('#btnClearSearch').click(function() {
            clearPalletSearch();
            $('#searchCountry, #searchOcf, #searchStyle').val('');
        });

        // ==========================================
        // --- Fullscreen Mode ---
        // ==========================================
        let fsZoom = 1.0;
        const FS_ZOOM_STEP = 0.1;
        const FS_ZOOM_MIN = 0.3;
        const FS_ZOOM_MAX = 2.0;
        let fsBoardScroll = null;

        function openFullscreen() {
            const overlay = document.getElementById('fullscreenOverlay');
            const fsBoard = document.getElementById('fullscreenBoard');

            // Clone the entire pallet board
            const originalBoard = document.getElementById('palletBoardScroll');
            const clone = originalBoard.cloneNode(true);
            clone.id = 'palletBoardScrollFs';
            // Re-bind click events on cloned pallet boxes
            clone.querySelectorAll('.pallet-box').forEach(function(box) {
                const onclick = box.getAttribute('onclick');
                if (onclick) {
                    box.addEventListener('click', function() {
                        eval(onclick);
                    });
                    box.removeAttribute('onclick');
                }
            });

            fsBoard.innerHTML = '';
            const wrapper = document.createElement('div');
            wrapper.className = 'pallet-board-wrapper';
            wrapper.style.minHeight = '100%';
            wrapper.appendChild(clone);
            fsBoard.appendChild(wrapper);

            fsBoardScroll = clone;
            setupDragScroll(fsBoardScroll);

            fsZoom = 1.0;
            applyFsZoom();

            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeFullscreen() {
            const overlay = document.getElementById('fullscreenOverlay');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('fullscreenBoard').innerHTML = '';
            fsBoardScroll = null;
        }

        function applyFsZoom() {
            if (!fsBoardScroll) return;
            fsBoardScroll.style.transform = `scale(${fsZoom})`;
            fsBoardScroll.style.transformOrigin = 'top left';
            document.getElementById('fsZoomLabel').textContent = Math.round(fsZoom * 100) + '%';
        }

        function syncFullscreenHighlight(matchedRows) {
            const fsBoard = document.getElementById('fullscreenBoard');
            if (!fsBoard || !fsBoard.querySelector('.pallet-row-card')) return;
            fsBoard.querySelectorAll('.pallet-row-card').forEach(function(card) {
                const row = card.getAttribute('data-row');
                card.classList.remove('search-match', 'search-dim');
                if (matchedRows && matchedRows.length > 0) {
                    if (matchedRows.includes(row)) card.classList.add('search-match');
                    else card.classList.add('search-dim');
                }
            });
        }

        document.getElementById('btnFullscreen').addEventListener('click', openFullscreen);
        document.getElementById('fsBtnClose').addEventListener('click', closeFullscreen);

        document.getElementById('fsBtnZoomIn').addEventListener('click', function() {
            fsZoom = Math.min(FS_ZOOM_MAX, Math.round((fsZoom + FS_ZOOM_STEP) * 10) / 10);
            applyFsZoom();
        });
        document.getElementById('fsBtnZoomOut').addEventListener('click', function() {
            fsZoom = Math.max(FS_ZOOM_MIN, Math.round((fsZoom - FS_ZOOM_STEP) * 10) / 10);
            applyFsZoom();
        });
        document.getElementById('fsBtnZoomReset').addEventListener('click', function() {
            fsZoom = 1.0;
            applyFsZoom();
        });

        // Mouse wheel zoom in fullscreen
        document.getElementById('fullscreenBoard').addEventListener('wheel', function(e) {
            if (e.ctrlKey) {
                e.preventDefault();
                if (e.deltaY < 0) {
                    fsZoom = Math.min(FS_ZOOM_MAX, Math.round((fsZoom + FS_ZOOM_STEP) * 10) / 10);
                } else {
                    fsZoom = Math.max(FS_ZOOM_MIN, Math.round((fsZoom - FS_ZOOM_STEP) * 10) / 10);
                }
                applyFsZoom();
            }
        }, { passive: false });

        // Fullscreen search
        document.getElementById('fsBtnSearch').addEventListener('click', function() {
            const country = document.getElementById('fsSearchCountry').value;
            const ocf = document.getElementById('fsSearchOcf').value;
            const style = document.getElementById('fsSearchStyle').value;
            runPalletSearch(country, ocf, style);
            // Also sync the normal search inputs
            $('#searchCountry').val(country);
            $('#searchOcf').val(ocf);
            $('#searchStyle').val(style);
        });
        ['fsSearchCountry', 'fsSearchOcf', 'fsSearchStyle'].forEach(function(id) {
            document.getElementById(id).addEventListener('keypress', function(e) {
                if (e.key === 'Enter') document.getElementById('fsBtnSearch').click();
            });
        });

        // Close fullscreen with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('fullscreenOverlay').classList.contains('active')) {
                closeFullscreen();
            }
        });
    </script>
</body>
</html>
