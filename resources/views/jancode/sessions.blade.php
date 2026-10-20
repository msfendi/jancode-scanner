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

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h1 class="h4 mb-0 text-gray-800">
                                <i class="fas fa-play-circle text-primary mr-1"></i> Sesi Import
                            </h1>
                            <p class="mb-0 text-muted small">
                                Langkah <strong>1</strong>: mulai session sebagai induk. Satu session mengikat satu
                                <strong>style / OCF / destination / warehouse</strong>.
                            </p>
                        </div>
                        <a href="{{ route('jancode.index') }}" class="btn btn-sm btn-outline-primary shadow-sm">
                            <i class="fas fa-file-import fa-sm mr-1"></i> Ke Import Packing List
                        </a>
                    </div>

                    @include('layout.workflow-steps', ['current' => 'session'])

                    @if ($current)
                        @include('layout.session-banner', ['currentSession' => $current])
                    @endif

                    <div class="row">
                        <!-- Start Session -->
                        <div class="col-lg-5 mb-4">
                            <div class="card border-0 shadow-lg session-hero h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="session-hero-icon mr-3">
                                            <i class="fas fa-layer-group"></i>
                                        </div>
                                        <div>
                                            <h5 class="text-white mb-0 font-weight-bold">Mulai Session Baru</h5>
                                            <div class="text-white-50 small">Kode session dibuat otomatis</div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('session.start') }}" id="startSessionForm">
                                        @csrf
                                        <div class="form-group mb-3">
                                            <label class="text-white-50 small font-weight-bold text-uppercase mb-1">
                                                Catatan <span class="text-white-50">(opsional)</span>
                                            </label>
                                            <textarea name="notes" id="sessionNotes" rows="3" maxlength="1000"
                                                class="form-control border-0 shadow-sm"
                                                placeholder="Contoh: Packing list buyer A, shift pagi, PO 12345...">{{ old('notes') }}</textarea>
                                        </div>

                                        <div class="session-code-preview mb-3">
                                            <div class="text-white-50 small text-uppercase font-weight-bold mb-1">Format kode</div>
                                            <code class="text-white">IMP-{{ now()->format('Ymd-His') }}-###</code>
                                        </div>

                                        <button type="submit" class="btn btn-light btn-block font-weight-bold text-primary shadow-sm">
                                            <i class="fas fa-play mr-1"></i> Start Session
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="col-lg-7 mb-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body p-4">
                                    <h6 class="font-weight-bold text-gray-800 mb-3">
                                        <i class="fas fa-info-circle text-primary mr-1"></i> Kenapa perlu session?
                                    </h6>
                                    <div class="row">
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex">
                                                <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                                                <div>
                                                    <div class="font-weight-bold small">Pemisah file identik</div>
                                                    <div class="text-muted small">
                                                        Dua file dengan style/OCF/destination/warehouse sama tetap terpisah
                                                        karena berada di session berbeda.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex">
                                                <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                                                <div>
                                                    <div class="font-weight-bold small">Satu induk data</div>
                                                    <div class="text-muted small">
                                                        Import, manage proforma, scan, dan pallet menempel pada session.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex">
                                                <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                                                <div>
                                                    <div class="font-weight-bold small">Revisi otomatis</div>
                                                    <div class="text-muted small">
                                                        Beberapa file Excel dalam session yang sama disimpan sebagai revisi.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex">
                                                <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                                                <div>
                                                    <div class="font-weight-bold small">Riwayat aman</div>
                                                    <div class="text-muted small">
                                                        Tutup session setelah selesai; data tetap bisa dibuka kembali.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-light border mb-0 py-2">
                                        <div class="small text-muted">
                                            <strong>Alur:</strong>
                                            <span class="text-primary font-weight-bold">Start Session</span>
                                            <i class="fas fa-chevron-right mx-1 small"></i> Import Excel
                                            <i class="fas fa-chevron-right mx-1 small"></i> Manage Proforma
                                            <i class="fas fa-chevron-right mx-1 small"></i> Scan
                                            <i class="fas fa-chevron-right mx-1 small"></i> Pallet
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistik ringkas -->
                    <div class="row mb-3">
                        <div class="col-6 col-xl-3 mb-3 mb-xl-0">
                            <div class="card border-0 shadow-sm h-100 session-stat-card">
                                <div class="card-body py-3 d-flex align-items-center">
                                    <div class="session-stat-icon" style="background:#eef2ff;color:#4e73df;">
                                        <i class="fas fa-layer-group"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="session-stat-value">{{ number_format($stats['total']) }}</div>
                                        <div class="session-stat-label">Total Session</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-xl-3 mb-3 mb-xl-0">
                            <div class="card border-0 shadow-sm h-100 session-stat-card">
                                <div class="card-body py-3 d-flex align-items-center">
                                    <div class="session-stat-icon" style="background:#e8faf2;color:#1cc88a;">
                                        <i class="fas fa-play-circle"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="session-stat-value">{{ number_format($stats['open']) }}</div>
                                        <div class="session-stat-label">Session Open</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-xl-3 mb-3 mb-xl-0">
                            <div class="card border-0 shadow-sm h-100 session-stat-card">
                                <div class="card-body py-3 d-flex align-items-center">
                                    <div class="session-stat-icon" style="background:#f1f3f5;color:#858796;">
                                        <i class="fas fa-stop-circle"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="session-stat-value">{{ number_format($stats['closed']) }}</div>
                                        <div class="session-stat-label">Session Closed</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-xl-3 mb-3 mb-xl-0">
                            <div class="card border-0 shadow-sm h-100 session-stat-card">
                                <div class="card-body py-3 d-flex align-items-center">
                                    <div class="session-stat-icon" style="background:#e8f7fb;color:#36b9cc;">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="session-stat-value">{{ number_format($stats['packing_lists']) }}</div>
                                        <div class="session-stat-label">Packing List</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Session -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
                            <div>
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-history mr-1"></i> Daftar Session
                                </h6>
                                <div class="small text-muted mt-1">
                                    Pilih session aktif, buka kembali, atau tutup session yang sudah selesai.
                                </div>
                            </div>
                            <span class="badge badge-primary badge-pill px-3 py-2 mt-2 mt-sm-0">
                                <i class="fas fa-database mr-1"></i> {{ $sessions->count() }} hasil
                            </span>
                        </div>
                        <div class="card-body pb-0">
                            <div class="session-filter-box mb-3">
                                <form method="GET" action="{{ route('session.index') }}" class="form-row align-items-end">
                                    <div class="col-lg-5 mb-1">
                                        <label class="small text-muted mb-0">
                                            Cari kode / style / OCF / destination / warehouse / catatan
                                        </label>
                                        <input type="text" name="q" value="{{ $search }}"
                                            class="form-control form-control-sm"
                                            placeholder="mis. TESTA1 / 90001 / JAPAN / WH1">
                                    </div>
                                    <div class="col-lg-3 mb-1">
                                        <label class="small text-muted mb-0">Status</label>
                                        <select name="status" class="form-control form-control-sm">
                                            <option value="">Semua status</option>
                                            <option value="open" {{ $status === 'open' ? 'selected' : '' }}>OPEN</option>
                                            <option value="closed" {{ $status === 'closed' ? 'selected' : '' }}>CLOSED</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-2 mb-1">
                                        <button type="submit" class="btn btn-sm btn-primary btn-block">
                                            <i class="fas fa-search mr-1"></i> Cari
                                        </button>
                                    </div>
                                    <div class="col-lg-2 mb-1">
                                        <a href="{{ route('session.index') }}" class="btn btn-sm btn-outline-secondary btn-block">
                                            <i class="fas fa-undo mr-1"></i> Reset
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table session-table table-hover mb-0" id="tableSessions" width="100%">
                                    <thead>
                                        <tr>
                                            <th class="pl-3">Session</th>
                                            <th>Packing Group</th>
                                            <th class="text-center">Revisi</th>
                                            <th class="text-center">Packing List</th>
                                            <th>Catatan</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-right pr-3">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($sessions as $s)
                                            @php $isCurrent = $current && $current->id === $s->id; @endphp
                                            <tr class="{{ $isCurrent ? 'session-row-active' : '' }}">
                                                <td class="pl-3" data-order="{{ $s->code }}">
                                                    <div class="d-flex align-items-center">
                                                        <div class="session-avatar {{ $isCurrent ? 'is-active' : '' }}">
                                                            <i class="fas {{ $isCurrent ? 'fa-play' : 'fa-layer-group' }}"></i>
                                                        </div>
                                                        <div class="ml-2">
                                                            <div class="font-weight-bold text-gray-800 session-code">{{ $s->code }}</div>
                                                            <div class="text-muted session-subline">
                                                                {{ $s->started_at ? $s->started_at->format('d/m/Y H:i') : '-' }}
                                                                @if ($s->user)
                                                                    &middot; {{ $s->user->name }}
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="font-weight-bold small text-gray-800">{{ $s->style_number ?: '-' }}</div>
                                                    <div class="session-subline text-muted" title="OCF/CPO: {{ $s->ocf_number ?: '-' }} · Destination: {{ $s->destination ?: '-' }} · Warehouse: {{ $s->warehouse ?: '-' }}">
                                                        <i class="fas fa-hashtag mr-1"></i>{{ $s->ocf_number ?: '-' }}
                                                        <span class="mx-1">&middot;</span><i class="fas fa-map-marker-alt mr-1"></i>{{ $s->destination ?: '-' }}
                                                        <span class="mx-1">&middot;</span><i class="fas fa-warehouse mr-1"></i>{{ $s->warehouse ?: '-' }}
                                                    </div>
                                                </td>
                                                <td class="text-center" data-order="{{ $s->import_batches_count }}">
                                                    <span class="badge badge-light border session-count-badge">{{ $s->import_batches_count }} rev</span>
                                                </td>
                                                <td class="text-center" data-order="{{ $s->packing_lists_count }}">
                                                    <span class="badge badge-light border session-count-badge">{{ $s->packing_lists_count }} PL</span>
                                                </td>
                                                <td>
                                                    <div class="text-muted small text-truncate" style="max-width: 220px;"
                                                        title="{{ $s->notes }}">
                                                        {{ $s->notes ?: '-' }}
                                                    </div>
                                                </td>
                                                <td class="text-center" data-order="{{ $s->isOpen() ? 1 : 0 }}">
                                                    @if ($s->isOpen())
                                                        <span class="session-status-pill session-status-open">
                                                            <span class="session-status-dot"></span> OPEN
                                                        </span>
                                                    @else
                                                        <span class="session-status-pill session-status-closed">
                                                            <span class="session-status-dot"></span> CLOSED
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="text-right pr-3 text-nowrap">
                                                    @if (!$isCurrent)
                                                        <form method="POST" action="{{ route('session.select', $s) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-primary"
                                                                title="Jadikan session aktif">
                                                                <i class="fas fa-play mr-1"></i> Gunakan
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="badge badge-primary px-3 py-2">
                                                            <i class="fas fa-check mr-1"></i> Aktif
                                                        </span>
                                                    @endif

                                                    @if ($s->isClosed())
                                                        <form method="POST" action="{{ route('session.reopen', $s) }}" class="d-inline"
                                                            onsubmit="return confirm('Buka kembali session {{ $s->code }} untuk melanjutkan pekerjaan?');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-success"
                                                                title="Buka kembali session (pekerjaan belum selesai)">
                                                                <i class="fas fa-undo"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('session.close', $s) }}" class="d-inline"
                                                            onsubmit="return confirm('Tutup session {{ $s->code }}? Import & scan baru tidak akan diizinkan.');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                                title="Tutup session">
                                                                <i class="fas fa-stop-circle"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="session-empty-row">
                                                <td colspan="7" class="text-center text-muted py-5">
                                                    <i class="fas fa-inbox fa-2x mb-2 d-block text-gray-300"></i>
                                                    @if ($search !== '' || $status)
                                                        Tidak ada session yang cocok dengan pencarian.
                                                    @else
                                                        Belum ada session. Mulai session pertama Anda di atas.
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            @include('layout.footer')

        </div>
    </div>

    <style>
        .session-hero {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 60%, #1b3a9c 100%);
            border-radius: .75rem;
        }
        .session-hero-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .18);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.2rem;
        }
        .session-code-preview code {
            background: rgba(0, 0, 0, .18);
            padding: .3rem .55rem;
            border-radius: .35rem;
            font-size: .9rem;
            letter-spacing: .03em;
        }
        .session-hero textarea::placeholder {
            color: #b7c4e8;
        }

        /* Ringkasan & tabel daftar session */
        .session-stat-card {
            border-radius: .7rem;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .session-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 .35rem .9rem rgba(58, 59, 69, .12) !important;
        }
        .session-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex: 0 0 44px;
        }
        .session-stat-value {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.1;
            color: #2e3a59;
        }
        .session-stat-label {
            font-size: .68rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #858796;
            font-weight: 800;
        }
        .session-filter-box {
            background: #f8f9fc;
            border: 1px solid #eaecf4;
            border-radius: .65rem;
            padding: .85rem 1rem;
        }
        .session-table thead th {
            border-top: 0;
            border-bottom: 1px solid #e3e6f0;
            background: #f8f9fc;
            color: #858796;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .055em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .session-table tbody td {
            border-top: 1px solid #f0f2f8;
            padding-top: .8rem;
            padding-bottom: .8rem;
        }
        .session-table tbody tr {
            transition: background .15s ease;
        }
        .session-table tbody tr:hover {
            background: #f8f9fc;
        }
        .session-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #eef2ff;
            color: #4e73df;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            flex: 0 0 38px;
        }
        .session-avatar.is-active {
            background: linear-gradient(135deg, #4e73df, #224abe);
            color: #fff;
            box-shadow: 0 4px 10px rgba(78, 115, 223, .3);
        }
        .session-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
            font-size: .84rem;
            letter-spacing: .01em;
        }
        .session-subline {
            font-size: .72rem;
        }
        .session-count-badge {
            font-size: .72rem;
            font-weight: 700;
        }
        .session-status-pill {
            display: inline-flex;
            align-items: center;
            padding: .32rem .62rem;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .05em;
        }
        .session-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            display: inline-block;
            margin-right: .35rem;
        }
        .session-status-open {
            background: #e8faf2;
            color: #1cc88a;
        }
        .session-status-closed {
            background: #eef0f3;
            color: #858796;
        }
        .session-row-active {
            background: #f7f9ff !important;
        }
        .session-row-active td:first-child {
            border-left: 3px solid #4e73df;
        }
        #tableSessions_wrapper .dataTables_length {
            padding: .85rem 1rem 0;
        }
        #tableSessions_wrapper .dataTables_info {
            padding: .6rem 1rem 1rem;
        }
        #tableSessions_wrapper .dataTables_paginate {
            padding: .35rem 1rem 1rem;
        }
    </style>

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script>
        $(function () {
            // Baris empty-state dari Blade dihapus agar DataTables menampilkan
            // pesan "zeroRecords" sendiri saat hasil filter kosong.
            $('#tableSessions tbody .session-empty-row').remove();

            $('#tableSessions').DataTable({
                searching: false,
                ordering: true,
                paging: true,
                info: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
                order: [[5, 'desc'], [0, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [6] }
                ],
                autoWidth: false,
                language: {
                    lengthMenu: 'Tampilkan _MENU_ session',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ session',
                    infoEmpty: 'Tidak ada session',
                    infoFiltered: '(difilter dari _MAX_ total session)',
                    zeroRecords: 'Tidak ada session yang cocok',
                    paginate: {
                        first: '«',
                        last: '»',
                        next: '›',
                        previous: '‹'
                    }
                }
            });
        });
    </script>

</body>
</html>
