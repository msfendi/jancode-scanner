<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@x.x.x/dist/select2-bootstrap4.min.css"
    rel="stylesheet" />

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
                            <h1 class="h4 mb-0 text-gray-800">Scanner Jancode (per Packing List)</h1>
                            <p class="mb-0 text-muted small">
                                Pilih packing list pada session aktif → sistem memuat semua jancode revisi <strong>aktif</strong>.
                                <strong>1 scan = 1 carton.</strong>
                            </p>
                        </div>
                        <div>
                            <a href="{{ route('jancode.packingLists') }}" class="btn btn-sm btn-outline-primary shadow-sm mr-2">
                                <i class="fas fa-clipboard-list fa-sm mr-1"></i> Packing List
                            </a>
                            <a href="{{ route('scanner.batches') }}" class="btn btn-sm btn-outline-primary shadow-sm mr-2">
                                <i class="fas fa-history fa-sm mr-1"></i> Riwayat Batch Scan
                            </a>
                            <a href="{{ route('pallet.index') }}" class="btn btn-sm btn-info shadow-sm">
                                <i class="fas fa-boxes fa-sm mr-1"></i> Manage Pallet
                            </a>
                        </div>
                    </div>

                    @include('layout.workflow-steps', ['current' => 'scan'])

                    @include('layout.session-banner', ['currentSession' => $currentSession])

                    @php $canScan = $currentSession && $currentSession->isOpen(); @endphp

                    @if (!$canScan)
                        <div class="card shadow mb-4 border-left-warning">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-lock fa-2x text-warning mb-3"></i>
                                <h5 class="font-weight-bold text-gray-800">Scan terkunci</h5>
                                <p class="text-muted mb-3">
                                    @if (!$currentSession)
                                        Mulai session terlebih dahulu sebelum melakukan scan.
                                    @else
                                        Session <strong>{{ $currentSession->code }}</strong> sudah ditutup.
                                        Pilih session lain atau mulai session baru.
                                    @endif
                                </p>
                                <a href="{{ route('session.index') }}" class="btn btn-primary font-weight-bold">
                                    <i class="fas fa-play-circle mr-1"></i> Ke Halaman Session
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- Pilih Packing List -->
                    <div class="card shadow mb-4 {{ $canScan ? '' : 'd-none' }}">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-clipboard-list mr-1"></i> 1. Pilih Packing List
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-8 mb-2">
                                    <div class="input-group">
                                        <select class="form-control" id="packingListSelect" style="width:100%"></select>
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" id="btn-load" type="button">
                                                <i class="fas fa-upload fa-sm mr-1"></i> Muat Packing List
                                            </button>
                                            <button class="btn btn-danger" id="btn-unload" type="button" style="display:none;">
                                                <i class="fas fa-times fa-sm mr-1"></i> Ganti
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Hanya packing list dengan revisi <strong>aktif</strong> yang muncul di daftar.
                                        <a href="{{ route('scanner.index') }}?monitor=1" class="font-weight-bold ml-1">
                                            <i class="fas fa-satellite-dish mr-1"></i>Mode Monitor Live
                                        </a>
                                    </small>
                                </div>
                                <div class="col-lg-4 mb-2" id="pl-info" style="display:none;">
                                    <div class="border rounded p-2 bg-light">
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">Packing List</small>
                                            <span id="pl-type" class="badge badge-primary">PROFORMA</span>
                                        </div>
                                        <p class="mb-1 font-weight-bold small" id="pl-label">-</p>
                                        <div class="d-flex justify-content-between small text-muted">
                                            <span id="pl-batch">-</span>
                                            <span id="pl-rev">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scan Input -->
                    <div class="card shadow mb-4" id="scan-card" style="display:none;">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-barcode mr-1"></i> 2. Scan Barcode Karton
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-8 mb-2">
                                    <div class="input-group">
                                        <input class="form-control form-control-lg" type="text" id="jancode"
                                            name="jancode" autocomplete="off" autofocus
                                            placeholder="Scan barcode karton di sini...">
                                        <div class="input-group-append">
                                            <button class="btn btn-success" id="btn-submit" type="button">
                                                <i class="fas fa-check fa-sm mr-1"></i> Submit
                                            </button>
                                            <button class="btn btn-warning" id="btn-rollback" type="button">
                                                <i class="fas fa-undo fa-sm mr-1"></i> Rollback
                                            </button>
                                            <button class="btn btn-outline-danger" id="btn-void" type="button">
                                                <i class="fas fa-backspace fa-sm mr-1"></i> Void Last
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Barcode harus cocok dengan salah satu jancode pada packing list. 1 scan = 1 carton.
                                    </small>
                                </div>
                                <div class="col-lg-4 mb-2 text-right">
                                    <label class="small text-muted mb-1 d-block">Cari jancode di daftar:</label>
                                    <input type="text" class="form-control form-control-sm" id="row-filter" placeholder="Filter jancode / size / color...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Counter Cards -->
                    <div class="row" id="counter-cards" style="display:none;">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Sudah Scan (Submit)</div>
                                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="count-scanned">0</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-check-circle fa-2x text-success"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Scan Sementara</div>
                                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="count-unsubmitted">0</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-pause-circle fa-2x text-warning"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Carton</div>
                                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="count-total">0</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-boxes fa-2x text-info"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-danger shadow h-100 py-2" id="card-balance">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1" id="label-balance">Balance</div>
                                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="count-balance">0</div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-balance-scale fa-2x text-danger" id="icon-balance"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="status-banner" style="display:none;" class="mb-4">
                        <div class="alert mb-0 text-center font-weight-bold" id="status-alert" role="alert"></div>
                    </div>

                    <!-- Progress tiap jancode -->
                    <div class="card shadow mb-4" id="rows-card" style="display:none;">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-list-ol mr-1"></i> Daftar Jancode pada Packing List (<span id="rows-done">0</span>/<span id="rows-total">0</span> selesai)
                            </h6>
                            <div class="d-flex align-items-center">
                                <span class="badge badge-secondary mr-3" id="scan-live-badge" title="Perubahan scan dari device lain tampil otomatis">
                                    <i class="fas fa-circle mr-1" style="font-size:8px;"></i><span id="scan-live-text">LIVE: -</span>
                                </span>
                                <small class="text-muted mr-3 d-none d-md-inline">
                                    <i class="fas fa-clock mr-1"></i> Terakhir diperbarui: <span id="rows-updated">-</span>
                                </small>
                                <div class="custom-control custom-switch mr-3">
                                    <input type="checkbox" class="custom-control-input" id="auto-refresh-toggle" checked>
                                    <label class="custom-control-label small text-muted" for="auto-refresh-toggle">Auto-refresh</label>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary" id="btn-refresh-rows" type="button">
                                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-bordered table-sm table-hover mb-0" id="table-rows">
                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 1;">
                                        <tr>
                                            <th class="text-center" style="width:45px;">#</th>
                                            <th>JAN Code</th>
                                            <th>Carton No</th>
                                            <th>Color</th>
                                            <th class="text-center">Size</th>
                                            <th class="text-right">Pcs/Ctn</th>
                                            <th class="text-right">Carton</th>
                                            <th class="text-right">Received</th>
                                            <th class="text-right">Sementara</th>
                                            <th class="text-right">Balance</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="rows-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Log Scan Realtime Card -->
                    <div class="card shadow mb-4" id="log-card" style="display:none;">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-history mr-1"></i> Log Scanning Realtime
                            </h6>
                            <div>
                                <span class="badge badge-danger mr-2 p-2" id="log-error-badge" style="display:none;">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> <span id="error-count-num">0</span>
                                    Scan Bermasalah
                                </span>
                                <button class="btn btn-sm btn-outline-secondary" id="btn-clear-log" type="button">
                                    <i class="fas fa-trash-alt mr-1"></i> Bersihkan Log Tampilan
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                                <table class="table table-bordered table-striped table-hover mb-0" id="table-scan-log">
                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 1;">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">#</th>
                                            <th style="width: 100px;">Waktu</th>
                                            <th>Barcode</th>
                                            <th style="width: 170px;" class="text-center">Status</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="log-tbody">
                                        <tr id="empty-log-row">
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="fas fa-barcode fa-2x mb-2 d-block"></i>
                                                Belum ada aktivitas scanning.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            @include('layout.footer')

        </div>

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <script type="text/javascript">
            $(document).ready(function () {

                $.ajaxSetup({
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                });

                // Aman bila CDN SweetAlert belum termuat
                function swalVisible() {
                    return typeof Swal !== 'undefined' && Swal.isVisible();
                }

                // ── Variables ──────────────────────────────────────────────
                let loadedBatchId = null;
                let scanStream = null;
                let scanStreamHash = '';
                let scanStreamRetry = null;
                let scanStreamOpenTimer = null;
                let pendingLiveState = null;
                let isScanning = false;
                let bufferTimer = null;
                let scanQueue = [];
                let scanLogIndex = 0;
                let errorCount = 0;

                const $input = $('#jancode');
                const $select = $('#packingListSelect');
                const $btnLoad = $('#btn-load');
                const $btnUnload = $('#btn-unload');

                const presetPackingList = new URLSearchParams(window.location.search).get('packing_list')
                    || @json($monitorBatchId ?? null);

                // ── Auto-focus input scan ──────────────────────────────────
                // Alat scanner mengirim karakter seperti keyboard, jadi fokus harus
                // selalu berada di input scan. Klik di area kosong / tab kembali
                // aktif akan mengembalikan fokus ke input.
                function focusScanInput() {
                    if (!$('#scan-card').is(':visible')) return;
                    if (swalVisible()) return;
                    $input.trigger('focus');
                }

                $(window).on('focus', focusScanInput);

                $(document).on('mouseup', function (e) {
                    if ($(e.target).closest('input, textarea, select, button, a, label, .select2-container, .swal2-container, .custom-control, .dropdown-menu').length) return;
                    focusScanInput();
                });

                // Fallback: bila fokus jatuh ke body, kembalikan ke input scan
                // begitu operator mulai mengetik / scan.
                $(document).on('keydown', function () {
                    if (swalVisible()) return;
                    const tag = (document.activeElement && document.activeElement.tagName) || '';
                    if (tag === 'BODY' || tag === 'HTML') { $input.trigger('focus'); }
                });

                // ── Select2 Init ───────────────────────────────────────────
                // select2 dimuat dari CDN; jika gagal, tetap bisa pakai <select> biasa.
                const hasSelect2 = typeof $.fn.select2 === 'function';

                function selectedPackingList() {
                    if (hasSelect2) {
                        const data = $select.select2('data')[0];
                        return data && data.id ? data : null;
                    }
                    const opt = $select.find('option:selected').get(0);
                    return opt && opt.value ? { id: opt.value, text: opt.text } : null;
                }

                if (hasSelect2) {
                    $select.select2({
                        theme: 'bootstrap4',
                        placeholder: 'Cari packing list (style / CPO / destination / warehouse)...',
                        allowClear: true,
                        ajax: {
                            url: "{{ route('scanner.packingLists') }}",
                            dataType: 'json',
                            delay: 250,
                            data: params => ({ q: params.term }),
                            processResults: data => ({ results: data }),
                            cache: true
                        }
                    });
                }

                // Prefill jika dibuka dari halaman packing list
                if (presetPackingList) {
                    $.get("{{ route('scanner.packingLists') }}", function (list) {
                        const found = (list || []).find(x => String(x.id) === String(presetPackingList));
                        if (found) {
                            const opt = new Option(found.text, found.id, true, true);
                            $select.append(opt).trigger('change');
                            loadPackingList();
                        }
                    });
                }

                // ── Load Packing List ──────────────────────────────────────
                $btnLoad.click(function () {
                    const data = selectedPackingList();
                    if (!data || !data.id) {
                        Swal.fire('Peringatan', 'Silakan pilih packing list terlebih dahulu', 'warning');
                        return;
                    }
                    loadPackingList();
                });

                function loadPackingList() {
                    const data = selectedPackingList();
                    if (!data || !data.id) return;

                    $btnLoad.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memuat...');

                    $.ajax({
                        url: "{{ route('scanner.load') }}",
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ import_batch_id: data.id }),
                        success: function (res) {
                            loadedBatchId = res.packing_list.id;
                            renderState(res);
                            $select.prop('disabled', true);
                            $btnLoad.hide();
                            $btnUnload.show();
                            $('#scan-card').show();
                            $('#log-card').show();
                            clearScanLogs();
                            $input.val('').focus();
                            startScanStream();
                        },
                        error: function (xhr) {
                            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Gagal memuat packing list';
                            Swal.fire('Gagal', msg, 'error');
                        },
                        complete: function () {
                            $btnLoad.prop('disabled', false).html('<i class="fas fa-upload fa-sm mr-1"></i> Muat Packing List');
                        }
                    });
                }

                $btnUnload.click(function () {
                    Swal.fire({
                        title: 'Ganti Packing List?',
                        text: 'Pastikan scan sementara sudah disubmit atau di-rollback.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Ganti',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.post("{{ route('scanner.reset') }}", function () {
                                loadedBatchId = null;
                                $select.prop('disabled', false).val(null).trigger('change');
                                $btnUnload.hide();
                                $btnLoad.show();
                                $('#scan-card').hide();
                                $('#log-card').hide();
                                $('#rows-card').hide();
                                $('#counter-cards').hide();
                                $('#pl-info').hide();
                                $input.val('');
                                clearScanLogs();
                                stopScanStream();
                                scanStreamHash = '';
                                pendingLiveState = null;
                            });
                        }
                    });
                });

                // ── Render state dari server ───────────────────────────────
                function renderState(res) {
                    if (!res || !res.packing_list) return;

                    const pl = res.packing_list;
                    $('#pl-info').show();
                    $('#pl-label').text(pl.group_label || '-');
                    $('#pl-batch').text(pl.batch_number || '-');
                    $('#pl-rev').text('Rev.' + pl.revision_no);
                    $('#pl-type').text((pl.doc_type || 'proforma').toUpperCase())
                        .removeClass('badge-primary badge-success')
                        .addClass(pl.doc_type === 'actual' ? 'badge-success' : 'badge-primary');

                    updateCounters(res.counters || {});
                    renderRows(res.rows || []);
                    markRowsUpdated();
                }

                function markRowsUpdated() {
                    const now = new Date();
                    $('#rows-updated').text(now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
                }

                function updateCounters(c) {
                    $('#counter-cards').show();
                    $('#count-scanned').text(c.scanned || 0);
                    $('#count-unsubmitted').text(c.unsubmitted || 0);
                    $('#count-total').text(c.total || 0);
                    $('#count-balance').text(c.balance || 0);

                    $('#rows-done').text(c.rows_done || 0);
                    $('#rows-total').text(c.rows_total || 0);

                    updateBalanceStyle(c.balance || 0, c.is_complete);
                }

                function updateBalanceStyle(balance, isComplete) {
                    const $card = $('#card-balance');
                    const $banner = $('#status-banner');
                    const $alert = $('#status-alert');

                    $card.removeClass('border-left-danger border-left-success border-left-warning');

                    if (balance < 0) {
                        $card.addClass('border-left-danger');
                        $banner.show();
                        $alert.removeClass('alert-success alert-info').addClass('alert-danger')
                            .html('<i class="fas fa-exclamation-triangle mr-1"></i> Over Scan!');
                    } else if (isComplete) {
                        $card.addClass('border-left-success');
                        $banner.show();
                        $alert.removeClass('alert-danger alert-info').addClass('alert-success')
                            .html('<i class="fas fa-check-circle mr-1"></i> Semua carton pada packing list ini sudah ter-scan');
                    } else {
                        $card.addClass('border-left-danger');
                        $banner.hide();
                    }
                }

                function rowStatusBadge(r) {
                    if (r.is_complete) return '<span class="badge badge-success">Lengkap</span>';
                    if (r.unsubmitted > 0) return '<span class="badge badge-warning text-dark">Ada sementara</span>';
                    return '<span class="badge badge-light border">Belum</span>';
                }

                function renderRows(rows) {
                    $('#rows-card').show();
                    const filter = ($('#row-filter').val() || '').toLowerCase();
                    let html = '';
                    let no = 0;

                    rows.forEach(function (r) {
                        if (filter) {
                            const hay = (r.jancode + ' ' + (r.size || '') + ' ' + (r.color || '')).toLowerCase();
                            if (hay.indexOf(filter) === -1) return;
                        }
                        no++;

                        html += `<tr data-jancode-id="${r.id}" class="${r.is_complete ? 'table-success' : ''}">
                            <td class="text-center text-muted">${no}</td>
                            <td><code>${r.jancode}</code></td>
                            <td class="small">${r.carton_no}</td>
                            <td class="small">${r.color || '-'}</td>
                            <td class="text-center"><span class="badge badge-primary">${r.size || '-'}</span></td>
                            <td class="text-right">${r.qty_per_carton}</td>
                            <td class="text-right font-weight-bold">${r.packing}</td>
                            <td class="text-right text-success font-weight-bold cell-receive">${r.receive}</td>
                            <td class="text-right text-warning font-weight-bold cell-unsub">${r.unsubmitted}</td>
                            <td class="text-right font-weight-bold cell-balance">${r.balance}</td>
                            <td class="text-center cell-status">${rowStatusBadge(r)}</td>
                        </tr>`;
                    });

                    if (no === 0) {
                        html = '<tr><td colspan="11" class="text-center text-muted py-3">Tidak ada jancode yang cocok.</td></tr>';
                    }

                    $('#rows-tbody').html(html);
                }

                // Update satu baris saja setelah scan (tanpa rebuild seluruh tabel),
                // supaya scan terasa instan walau packing list berisi banyak baris.
                function updateRowFromScan(res, jancodeId) {
                    if (!jancodeId) return;
                    const $tr = $('#rows-tbody tr[data-jancode-id="' + jancodeId + '"]');
                    if (!$tr.length) return;   // baris tidak tampil (mis. sedang difilter)
                    const row = res.row || (res.rows || []).find(function (r) { return String(r.id) === String(jancodeId); });
                    if (!row) return;

                    $tr.find('.cell-receive').text(row.receive);
                    $tr.find('.cell-unsub').text(row.unsubmitted);
                    $tr.find('.cell-balance').text(row.balance);
                    $tr.find('.cell-status').html(rowStatusBadge(row));
                    $tr.toggleClass('table-success', !!row.is_complete);
                }

                $('#row-filter').on('keyup', refreshRows);
                $('#btn-refresh-rows').click(refreshRows);

                function refreshRows() {
                    if (!loadedBatchId) return;
                    $.get("{{ route('scanner.rows') }}", function (res) {
                        renderState(res);
                    });
                }

                // ── Live update (SSE) + fallback polling ──────────────────
                // Perubahan scan dari device lain dikirim server lewat SSE
                // (/scanner/stream), polling 15 detik tetap dipakai sebagai cadangan.
                const AUTO_REFRESH_MS = 15000;
                let autoRefreshTimer = null;

                function startAutoRefresh() {
                    stopAutoRefresh();
                    autoRefreshTimer = setInterval(pollRows, AUTO_REFRESH_MS);
                    startScanStream();
                }

                function stopAutoRefresh() {
                    if (autoRefreshTimer) { clearInterval(autoRefreshTimer); autoRefreshTimer = null; }
                    stopScanStream();
                }

                function pollRows() {
                    if (!loadedBatchId) return;
                    if (isScanning || scanQueue.length > 0) return;   // sedang scan
                    if (document.hidden) return;                        // tab tidak aktif
                    if (swalVisible()) return;                       // ada dialog
                    if ($('#row-filter').val()) return;                 // sedang memfilter
                    refreshRows();
                }

                function setScanLive(state) {
                    const $badge = $('#scan-live-badge');
                    const $text = $('#scan-live-text');
                    $badge.removeClass('badge-secondary badge-success badge-warning badge-danger');

                    if (state === 'on') {
                        $badge.addClass('badge-success');
                        $text.text('LIVE');
                    } else if (state === 'connecting') {
                        $badge.addClass('badge-warning text-dark');
                        $text.text('LIVE: menghubungkan…');
                    } else if (state === 'polling') {
                        $badge.addClass('badge-warning text-dark');
                        $text.text('LIVE: polling 15s');
                    } else {
                        $badge.addClass('badge-secondary');
                        $text.text('LIVE: off');
                    }
                }

                function stopScanStream() {
                    if (scanStream) {
                        scanStream.close();
                        scanStream = null;
                    }
                    if (scanStreamRetry) {
                        clearTimeout(scanStreamRetry);
                        scanStreamRetry = null;
                    }
                    if (scanStreamOpenTimer) {
                        clearTimeout(scanStreamOpenTimer);
                        scanStreamOpenTimer = null;
                    }
                }

                function startScanStream() {
                    stopScanStream();

                    if (!loadedBatchId || !window.EventSource) {
                        setScanLive('off');
                        return;
                    }

                    setScanLive('connecting');

                    const url = "{{ route('scanner.stream') }}" + '?t=' + encodeURIComponent(scanStreamHash || '');
                    scanStream = new EventSource(url);

                    // Bila koneksi tidak terbuka dalam 8 detik, pakai polling sebagai fallback.
                    scanStreamOpenTimer = setTimeout(function () {
                        if (scanStream && scanStream.readyState === 0) {
                            setScanLive('polling');
                        }
                    }, 8000);

                    scanStream.addEventListener('open', function () {
                        if (scanStreamOpenTimer) {
                            clearTimeout(scanStreamOpenTimer);
                            scanStreamOpenTimer = null;
                        }
                        setScanLive('on');
                    });

                    scanStream.addEventListener('state', function (ev) {
                        let data = null;
                        try { data = JSON.parse(ev.data); } catch (e) { return; }

                        scanStreamHash = data.hash || scanStreamHash;

                        // Jangan ganggu operator yang sedang scan / memfilter / ada dialog.
                        if (isScanning || scanQueue.length > 0 || swalVisible() || $('#row-filter').val() || document.hidden) {
                            pendingLiveState = data;
                            return;
                        }

                        applyLiveState(data);
                    });

                    scanStream.addEventListener('done', function () {
                        stopScanStream();
                        setScanLive('connecting');
                        scanStreamRetry = setTimeout(startScanStream, 1500);
                    });

                    scanStream.onerror = function () {
                        stopScanStream();
                        setScanLive('off');
                        scanStreamRetry = setTimeout(startScanStream, 3000);
                    };
                }

                function applyLiveState(data) {
                    if (!data || !data.packing_list) return;

                    // Simpan nilai received sebelum render, untuk highlight baris yang berubah.
                    const before = {};
                    $('#rows-tbody tr[data-jancode-id]').each(function () {
                        before[String($(this).attr('data-jancode-id'))] = $.trim($(this).find('.cell-receive').text());
                    });

                    renderState(data);

                    Object.keys(before).forEach(function (id) {
                        const $tr = $('#rows-tbody tr[data-jancode-id="' + id + '"]');
                        if (!$tr.length) return;
                        const now = $.trim($tr.find('.cell-receive').text());
                        if (now !== before[id]) {
                            $tr.addClass('table-info');
                            setTimeout(function () { $tr.removeClass('table-info'); }, 2500);
                        }
                    });

                    setScanLive('on');
                }

                // Terapkan state yang tertunda setelah operator selesai scan / menutup dialog.
                setInterval(function () {
                    if (!pendingLiveState) return;
                    if (isScanning || scanQueue.length > 0 || swalVisible() || $('#row-filter').val() || document.hidden) return;
                    const data = pendingLiveState;
                    pendingLiveState = null;
                    applyLiveState(data);
                }, 1200);

                $('#auto-refresh-toggle').on('change', function () {
                    if ($(this).is(':checked')) { startAutoRefresh(); } else { stopAutoRefresh(); }
                });

                // Saat tab kembali aktif, langsung sinkronkan sekali
                document.addEventListener('visibilitychange', function () {
                    if (!document.hidden && $('#auto-refresh-toggle').is(':checked')) {
                        pollRows();
                        startScanStream();
                    }
                });

                window.addEventListener('beforeunload', stopScanStream);

                startAutoRefresh();

                // ── Log helper ─────────────────────────────────────────────
                function escapeHtml(str) { return $('<div>').text(str || '').html(); }

                function buildLogMeta(statusType, barcode) {
                    const display = escapeHtml(barcode);

                    if (statusType === 'pending') {
                        return {
                            rowClass: 'table-info', isError: false, barcodeHtml: display,
                            badge: '<span class="badge badge-info px-2 py-1"><i class="fas fa-spinner fa-spin mr-1"></i> Memproses</span>'
                        };
                    }
                    if (statusType === 'over') {
                        return {
                            rowClass: 'table-danger text-danger font-weight-bold', isError: true,
                            barcodeHtml: '<span class="text-danger font-weight-bold">' + display + '</span>',
                            badge: '<span class="badge badge-danger px-2 py-1"><i class="fas fa-exclamation-triangle mr-1"></i> Over Scan</span>'
                        };
                    }
                    if (statusType === 'different') {
                        return {
                            rowClass: 'table-danger text-danger font-weight-bold', isError: true,
                            barcodeHtml: '<span class="badge badge-danger p-1 font-weight-bold" style="font-size:95%;"><i class="fas fa-exclamation-triangle mr-1"></i>' + display + '</span>',
                            badge: '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> Tidak Cocok</span>'
                        };
                    }
                    if (statusType === 'submitted') {
                        return {
                            rowClass: 'table-success text-success', isError: false, barcodeHtml: display,
                            badge: '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-double mr-1"></i> Disubmit</span>'
                        };
                    }
                    return {
                        rowClass: 'table-success', isError: false, barcodeHtml: display,
                        badge: '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Cocok</span>'
                    };
                }

                function registerLogError(isError) {
                    if (!isError) return;
                    errorCount++;
                    $('#log-error-badge').show();
                    $('#error-count-num').text(errorCount);
                }

                // Tambahkan baris log. Mengembalikan id baris agar bisa di-update lagi.
                function addScanLogRow(barcode, statusType, message, timeOverride) {
                    $('#empty-log-row').hide();
                    scanLogIndex++;

                    const now = new Date();
                    const timeStr = timeOverride || now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    const meta = buildLogMeta(statusType, barcode);
                    registerLogError(meta.isError);

                    const rowId = 'log-row-' + scanLogIndex + '-' + now.getTime();
                    $('#log-tbody').prepend(`
                        <tr id="${rowId}" class="${meta.rowClass}">
                            <td class="text-center font-weight-bold">${scanLogIndex}</td>
                            <td><small class="font-weight-bold">${timeStr}</small></td>
                            <td class="log-barcode">${meta.barcodeHtml}</td>
                            <td class="text-center log-status">${meta.badge}</td>
                            <td class="log-message"><small>${escapeHtml(message)}</small></td>
                        </tr>
                    `);
                    $('#log-card').show();
                    return rowId;
                }

                // Baris log "optimistic": tampil segera sebelum server menjawab.
                function addPendingLogRow(barcode) {
                    return addScanLogRow(barcode, 'pending', 'Mengirim ke server...');
                }

                // Ubah baris pending menjadi hasil akhir.
                function resolveLogRow(rowId, statusType, message) {
                    const $tr = $('#' + rowId);
                    if (!$tr.length) { return; }
                    const barcode = $tr.find('.log-barcode').text().trim();
                    const meta = buildLogMeta(statusType, barcode);
                    registerLogError(meta.isError);
                    $tr.attr('class', meta.rowClass);
                    $tr.find('.log-status').html(meta.badge);
                    $tr.find('.log-message').html('<small>' + escapeHtml(message) + '</small>');
                }

                function clearScanLogs() {
                    scanLogIndex = 0; errorCount = 0;
                    $('#error-count-num').text(0);
                    $('#log-error-badge').hide();
                    $('#log-tbody').html(`
                        <tr id="empty-log-row">
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="fas fa-barcode fa-2x mb-2 d-block"></i>
                                Belum ada aktivitas scanning.
                            </td>
                        </tr>
                    `);
                }

                $('#btn-clear-log').click(clearScanLogs);

                // ── Input handling ─────────────────────────────────────────
                let lastQueued = '';
                let lastQueuedTime = 0;

                $input.on('keydown', function (e) {
                    if (e.key === 'Enter' || e.keyCode === 13 || e.key === 'Tab' || e.keyCode === 9) {
                        e.preventDefault();
                        clearTimeout(bufferTimer);
                        queueScan();
                    }
                });

                $input.on('input', function () {
                    clearTimeout(bufferTimer);
                    const val = $input.val().trim();
                    if (!val) return;
                    // Barcode JAN umumnya 13 digit; beri jeda pendek setelah panjang tercapai
                    bufferTimer = setTimeout(queueScan, val.length >= 13 ? 30 : 500);
                });

                function queueScan() {
                    const jancode = $input.val().trim();
                    if (!jancode) return;
                    if (!loadedBatchId) return;   // belum ada packing list yang dimuat

                    const now = Date.now();
                    if (jancode === lastQueued && (now - lastQueuedTime) < 100) { $input.val(''); return; }
                    lastQueued = jancode; lastQueuedTime = now;
                    $input.val('');

                    // Baris log langsung tampil (optimistic) supaya operator dapat
                    // umpan balik instan tanpa menunggu balasan server.
                    const rowId = addPendingLogRow(jancode);
                    scanQueue.push({ jancode: jancode, rowId: rowId });
                    processQueue();
                }

                function processQueue() {
                    if (isScanning || scanQueue.length === 0 || !loadedBatchId) return;

                    const item = scanQueue.shift();
                    const jancode = item.jancode;
                    const rowId = item.rowId;
                    isScanning = true;

                    $.ajax({
                        url: "{{ route('scanner.scan') }}",
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ jancode: jancode, count: 1 }),
                        success: function (res) {
                            // Update ringan: counter + satu baris saja (tanpa rebuild tabel).
                            updateCounters(res.counters || {});
                            markRowsUpdated();
                            updateRowFromScan(res, res.jancode_id);
                            resolveLogRow(rowId, 'match', 'Scan berhasil (sementara)');
                        },
                        error: function (xhr) {
                            const data = xhr.responseJSON || {};
                            if (data.error === 'over' || data.error === 'not_found') {
                                if (data.counters) updateCounters(data.counters);
                                resolveLogRow(rowId, data.error === 'over' ? 'over' : 'different', data.message || 'Scan gagal');
                            } else if (data.error === 'not_active') {
                                resolveLogRow(rowId, 'different', data.message || 'Revisi aktif berubah');
                                Swal.fire('Packing List Berubah', data.message || 'Revisi aktif berubah.', 'warning').then(() => location.reload());
                            } else {
                                resolveLogRow(rowId, 'different', data.message || 'Terjadi kesalahan scan');
                                refreshRows();
                            }
                        },
                        complete: function () {
                            isScanning = false;
                            if (loadedBatchId && !swalVisible()) $input.focus();
                            processQueue();
                        }
                    });
                }

                // ── Void / Rollback / Submit ───────────────────────────────
                $('#btn-void').on('click', function () {
                    if (!loadedBatchId) return;
                    Swal.fire({
                        title: 'Void scan terakhir?',
                        text: 'Scan terakhir pada packing list ini akan dihapus.',
                        icon: 'warning', showCancelButton: true,
                        confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, void!', cancelButtonText: 'Batal'
                    }).then(function (result) {
                        if (!result.isConfirmed) { $input.focus(); return; }
                        $.ajax({
                            url: "{{ route('scanner.void') }}",
                            type: 'DELETE',
                            contentType: 'application/json',
                            data: JSON.stringify({}),
                            success: function (res) { renderState(res); addScanLogRow('-', 'different', 'Scan terakhir di-void'); },
                            error: function (xhr) {
                                const data = xhr.responseJSON || {};
                                Swal.fire('Gagal void', data.error || data.message || 'Error', 'error');
                            },
                            complete: function () { $input.focus(); }
                        });
                    });
                });

                $('#btn-rollback').on('click', function () {
                    if (!loadedBatchId) return;
                    const unsubmitted = parseInt($('#count-unsubmitted').text()) || 0;
                    if (unsubmitted <= 0) {
                        Swal.fire({ icon: 'info', title: 'Tidak ada data rollback', confirmButtonText: 'OK' });
                        return;
                    }
                    Swal.fire({
                        title: 'Rollback semua scan sementara?',
                        text: unsubmitted + ' scan yang belum disubmit akan dihapus permanen!',
                        icon: 'warning', showCancelButton: true,
                        confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, hapus!', cancelButtonText: 'Batal'
                    }).then(function (result) {
                        if (!result.isConfirmed) { $input.focus(); return; }
                        $.ajax({
                            url: "{{ route('scanner.rollback') }}",
                            type: 'DELETE',
                            contentType: 'application/json',
                            data: JSON.stringify({}),
                            success: function (res) { renderState(res); addScanLogRow('-', 'different', res.message || 'Scan sementara di-rollback'); },
                            error: function (xhr) {
                                const data = xhr.responseJSON || {};
                                Swal.fire('Gagal Rollback', data.error || data.message || 'Error', 'error');
                            },
                            complete: function () { $input.focus(); }
                        });
                    });
                });

                $('#btn-submit').on('click', function () {
                    if (!loadedBatchId) return;
                    const unsubmitted = parseInt($('#count-unsubmitted').text()) || 0;
                    if (unsubmitted <= 0) {
                        Swal.fire({ icon: 'info', title: 'Tidak ada scan sementara', text: 'Belum ada scan baru.', confirmButtonText: 'OK' });
                        return;
                    }

                    Swal.fire({
                        title: 'Submit batch scan?',
                        text: unsubmitted + ' carton akan disubmit.',
                        icon: 'question', showCancelButton: true,
                        confirmButtonColor: '#28a745', cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, Submit!', cancelButtonText: 'Batal'
                    }).then(function (result) {
                        if (!result.isConfirmed) { $input.focus(); return; }
                        $.ajax({
                            url: "{{ route('scanner.submit') }}",
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({}),
                            success: function (res) {
                                renderState(res);
                                addScanLogRow('-', 'submitted', unsubmitted + ' carton berhasil disubmit');
                                const batchInfo = res.batch_no ? `<br><span class="badge badge-dark px-2 py-1">${res.batch_no}</span><br><small class="text-primary font-weight-bold">Akumulasi: ${res.counters.scanned} / ${res.counters.total} (Sisa: ${res.counters.balance})</small>` : '';
                                Swal.fire({ icon: 'success', title: 'Batch Scan Disubmit!', html: `<strong>${unsubmitted} carton</strong> tersimpan.${batchInfo}` });
                            },
                            error: function (xhr) {
                                const data = xhr.responseJSON || {};
                                Swal.fire('Gagal Submit!', data.message || data.error || 'Error', 'error');
                            },
                            complete: function () { $input.focus(); }
                        });
                    });
                });

            });
        </script>

</html>
