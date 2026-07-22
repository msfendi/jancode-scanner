<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@x.x.x/dist/select2-bootstrap4.min.css"
    rel="stylesheet" />

<body id="page-top">
    <!-- Page Wrapper -->
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">
                @include('layout.navbar')
                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Scanner Jancode</h1>
                    </div>

                    <!-- Scan Input Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-barcode mr-1"></i> Scan Barcode
                            </h6>
                        </div>
                        <div class="card-body">

                            <div class="row">
                                <!-- Target Selection -->
                                <div class="col-lg-6 mb-3">
                                    <label>Pilih Target (Jancode/Size) :</label>
                                    <div class="input-group">
                                        <select class="form-control" id="targetSelect" name="targetSelect">
                                            <!-- Select2 options via AJAX -->
                                        </select>
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" id="btn-lock" type="button">
                                                <i class="fas fa-lock fa-sm mr-1"></i> Kunci Target
                                            </button>
                                            <button class="btn btn-danger" id="btn-unlock" type="button"
                                                style="display:none;">
                                                <i class="fas fa-unlock fa-sm mr-1"></i> Ganti
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Cari target yang ingin di-scan berdasarkan
                                        Jancode, Size, Color, atau Qty.</small>
                                </div>

                                <!-- Input -->
                                <div class="col-lg-6 mb-3">
                                    <label>Scan Barcode Fisik :</label>
                                    <div class="input-group">
                                        <input class="form-control form-control-lg" type="text" id="jancode"
                                            name="jancode" autocomplete="off" placeholder="Scan barcode disini..."
                                            disabled>
                                        <div class="input-group-append">
                                            <button class="btn btn-success" id="btn-submit" type="button" disabled>
                                                <i class="fas fa-check fa-sm mr-1"></i> Submit
                                            </button>
                                            <button class="btn btn-warning" id="btn-rollback" type="button" disabled>
                                                <i class="fas fa-undo fa-sm mr-1"></i> Rollback
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Pilih target terlebih dahulu sebelum mulai scan.
                                    </small>
                                </div>

                                <!-- Info Produk -->
                                <div class="col-lg-6" id="info-box" style="display:none;">
                                    <label>Info Produk :</label>
                                    <div class="border rounded p-2 bg-light">
                                        <div class="row">
                                            <div class="col-12 mb-1">
                                                <small class="text-muted">Deskripsi</small>
                                                <p class="mb-0 font-weight-bold small" id="info-desc">-</p>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Size</small>
                                                <p class="mb-0" id="info-size">-</p>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Color</small>
                                                <p class="mb-0" id="info-color">-</p>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Jancode</small>
                                                <p class="mb-0 small" id="info-jancode">-</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Counter Cards -->
                    <div class="row" id="counter-cards" style="display:none;">

                        <!-- Scanned -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Sudah Scan</div>
                                            <div class="h3 mb-0 font-weight-bold text-gray-800" id="count-scanned">0
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-check-circle fa-2x text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scan Sementara -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Scan Sementara</div>
                                            <div class="h3 mb-0 font-weight-bold text-gray-800" id="count-unsubmitted">0
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-pause-circle fa-2x text-warning"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Qty -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Total Qty</div>
                                            <div class="h3 mb-0 font-weight-bold text-gray-800" id="count-total">0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-boxes fa-2x text-info"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Balance -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-danger shadow h-100 py-2" id="card-balance">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1"
                                                id="label-balance">
                                                Balance</div>
                                            <div class="h3 mb-0 font-weight-bold text-gray-800" id="count-balance">0
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-balance-scale fa-2x text-danger" id="icon-balance"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Status Banner -->
                    <div id="status-banner" style="display:none;" class="mb-4">
                        <div class="alert mb-0 text-center font-weight-bold" id="status-alert" role="alert"></div>
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
                                    Jancode Beda / Tidak Cocok
                                </span>
                                <button class="btn btn-sm btn-outline-secondary" id="btn-clear-log" type="button">
                                    <i class="fas fa-trash-alt mr-1"></i> Bersihkan Log Tampilan
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                <table class="table table-bordered table-striped table-hover mb-0" id="table-scan-log">
                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 1;">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">#</th>
                                            <th style="width: 100px;">Waktu</th>
                                            <th>Barcode Fisik</th>
                                            <th>Target Barcode</th>
                                            <th style="width: 160px;" class="text-center">Status</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="log-tbody">
                                        <tr id="empty-log-row">
                                            <td colspan="6" class="text-center text-muted py-4">
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
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            @include('layout.footer')

        </div>
        <!-- End of Content Wrapper -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <script type="text/javascript">
            $(document).ready(function () {

                $.ajaxSetup({
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                });

                // ── Variables ──────────────────────────────────────────────
                let lockedTargetId = null;
                let lockedTargetJancode = null;
                let isScanning = false;
                let bufferTimer = null;
                const BUFFER_DELAY = 200;
                let scanQueue = [];

                let scanLogIndex = 0;
                let wrongScanCount = 0;

                const $input = $('#jancode');
                const $targetSelect = $('#targetSelect');
                const $btnLock = $('#btn-lock');
                const $btnUnlock = $('#btn-unlock');
                const $btnSubmit = $('#btn-submit');
                const $btnRollback = $('#btn-rollback');

                // ── Select2 Init ───────────────────────────────────────────
                $targetSelect.select2({
                    theme: 'bootstrap4',
                    placeholder: 'Cari jancode / ukuran...',
                    allowClear: true,
                    ajax: {
                        url: '/scanner/search',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term };
                        },
                        processResults: function (data) {
                            return { results: data };
                        },
                        cache: true
                    }
                });

                // ── Helper: Add Scan Log Row ──────────────────────────────
                function addScanLogRow(scannedBarcode, targetBarcode, isMatch, statusType, message, timeOverride) {
                    $('#empty-log-row').hide();
                    scanLogIndex++;

                    const now = new Date();
                    const timeStr = timeOverride || now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                    function escapeHtml(str) {
                        return $('<div>').text(str || '').html();
                    }

                    let rowClass = '';
                    let statusBadge = '';
                    let barcodeDisplay = escapeHtml(scannedBarcode);
                    let targetDisplay = escapeHtml(targetBarcode || lockedTargetJancode || '-');

                    if (statusType === 'different' || !isMatch) {
                        wrongScanCount++;
                        rowClass = 'table-danger text-danger font-weight-bold';
                        statusBadge = '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> Beda Jancode</span>';
                        barcodeDisplay = '<span class="badge badge-danger p-1 font-weight-bold" style="font-size:95%;"><i class="fas fa-exclamation-triangle mr-1"></i>' + barcodeDisplay + '</span>';

                        $('#log-error-badge').show();
                        $('#error-count-num').text(wrongScanCount);
                    } else if (statusType === 'over') {
                        wrongScanCount++;
                        rowClass = 'table-danger text-danger font-weight-bold';
                        statusBadge = '<span class="badge badge-danger px-2 py-1"><i class="fas fa-exclamation-triangle mr-1"></i> Over Scan</span>';
                        barcodeDisplay = '<span class="text-danger font-weight-bold">' + barcodeDisplay + '</span>';

                        $('#log-error-badge').show();
                        $('#error-count-num').text(wrongScanCount);
                    } else if (statusType === 'submitted') {
                        rowClass = 'table-success text-success';
                        statusBadge = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-double mr-1"></i> Disubmit</span>';
                    } else if (statusType === 'saved') {
                        rowClass = '';
                        statusBadge = '<span class="badge badge-info px-2 py-1"><i class="fas fa-database mr-1"></i> Tersimpan</span>';
                    } else {
                        rowClass = 'table-success';
                        statusBadge = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Cocok</span>';
                    }

                    const trHtml = `
                    <tr class="${rowClass}">
                        <td class="text-center font-weight-bold">${scanLogIndex}</td>
                        <td><small class="font-weight-bold">${timeStr}</small></td>
                        <td>${barcodeDisplay}</td>
                        <td><small class="text-muted font-weight-bold">${targetDisplay}</small></td>
                        <td class="text-center">${statusBadge}</td>
                        <td><small>${escapeHtml(message)}</small></td>
                    </tr>
                `;

                    $('#log-tbody').prepend(trHtml);
                    $('#log-card').show();
                }

                function clearScanLogs() {
                    scanLogIndex = 0;
                    wrongScanCount = 0;
                    $('#error-count-num').text(0);
                    $('#log-error-badge').hide();
                    $('#log-tbody').html(`
                    <tr id="empty-log-row">
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-barcode fa-2x mb-2 d-block"></i>
                            Belum ada aktivitas scanning.
                        </td>
                    </tr>
                `);
                }

                $('#btn-clear-log').click(function () {
                    clearScanLogs();
                });

                // ── Lock/Unlock Logic ──────────────────────────────────────
                $btnLock.click(function () {
                    const data = $targetSelect.select2('data')[0];
                    if (!data || !data.id) {
                        Swal.fire('Peringatan', 'Silakan pilih target terlebih dahulu', 'warning');
                        return;
                    }

                    lockedTargetId = data.id;
                    lockedTargetJancode = data.jancode;

                    $targetSelect.prop('disabled', true);
                    $btnLock.hide();
                    $btnUnlock.show();

                    $input.prop('disabled', false).focus();
                    $btnSubmit.prop('disabled', false);
                    $btnRollback.prop('disabled', false);

                    $('#info-jancode').text(data.jancode || '-');
                    $('#info-size').text(data.size || '-');

                    clearScanLogs();
                    $('#log-card').show();

                    refreshCount();
                });

                $btnUnlock.click(function () {
                    Swal.fire({
                        title: 'Ganti Target?',
                        text: 'Pastikan scan sebelumnya sudah di-submit',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Ganti',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.post('/scanner/reset', function () {
                                lockedTargetId = null;
                                lockedTargetJancode = null;
                                $targetSelect.prop('disabled', false).val(null).trigger('change');
                                $btnUnlock.hide();
                                $btnLock.show();

                                $input.prop('disabled', true).val('');
                                $btnSubmit.prop('disabled', true);
                                $btnRollback.prop('disabled', true);

                                $('#info-box').hide();
                                $('#counter-cards').hide();
                                clearScanLogs();
                                $('#log-card').hide();
                            });
                        }
                    });
                });

                // ── Input Handling ─────────────────────────────────────────
                let lastQueuedJancode = '';
                let lastQueuedTime = 0;

                $input.on('keydown', function (e) {
                    if (e.key === 'Enter' || e.keyCode === 13 || e.key === 'Tab' || e.keyCode === 9) {
                        e.preventDefault();
                        clearTimeout(bufferTimer);
                        queueScan();
                        return;
                    }
                });

                $input.on('input', function () {
                    clearTimeout(bufferTimer);

                    const val = $input.val().trim();
                    if (!val) return;

                    const targetLen = (lockedTargetJancode && lockedTargetJancode.length > 0) ? lockedTargetJancode.length : 13;

                    // Jika panjang barcode sudah mencapai/melebihi target Jancode (misal 13 digit),
                    // tunggu delay sangat singkat (30ms) untuk menangkap suffix Enter jika ada.
                    if (val.length >= targetLen) {
                        bufferTimer = setTimeout(function () {
                            queueScan();
                        }, 30);
                    } else {
                        // Jika belum mencapai panjang target, tunggu delay 500ms agar input scanner tidak terpotong di tengah jalan
                        bufferTimer = setTimeout(function () {
                            queueScan();
                        }, 500);
                    }
                });

                // ── Queue Scan ─────────────────────────────────────────────
                function queueScan() {
                    const jancode = $input.val().trim();
                    if (!jancode) return;

                    const now = Date.now();
                    // Cegah pemicuan ganda dalam kurun waktu 100ms untuk barcode yang persis sama
                    if (jancode === lastQueuedJancode && (now - lastQueuedTime) < 100) {
                        $input.val('');
                        return;
                    }

                    lastQueuedJancode = jancode;
                    lastQueuedTime = now;
                    $input.val('');

                    if (jancode === lockedTargetJancode) {
                        let scanned = parseInt($('#count-scanned').text()) || 0;
                        let unsubmitted = parseInt($('#count-unsubmitted').text()) || 0;
                        let total = parseInt($('#count-total').text()) || 0;

                        // Hanya lakukan optimistic UI jika total sudah diketahui dari server
                        if (total > 0) {
                            $('#count-unsubmitted').text(unsubmitted + 1);
                            let balance = total - (scanned + unsubmitted + 1);
                            $('#count-balance').text(balance);
                            updateBalanceStyle(balance, false);
                        }
                    }

                    scanQueue.push(jancode);
                    processQueue();
                }

                // ── Process Queue ──────────────────────────────────────────
                function processQueue() {
                    if (isScanning || scanQueue.length === 0 || !lockedTargetId) return;

                    // Ambil 1 per 1 dari antrean (FIFO) agar tidak ada barcode yang tergabung/terlewat saat scan cepat
                    const jancode = scanQueue.shift();

                    isScanning = true;

                    $.ajax({
                        url: '/scanner/scan',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            jancode: jancode,
                            jancode_id: lockedTargetId,
                            count: 1
                        }),
                        success: function (response) {
                            updateCounters(response);

                            // Dynamic log entry
                            const isMatch = (jancode === lockedTargetJancode);
                            addScanLogRow(
                                jancode,
                                lockedTargetJancode,
                                isMatch,
                                isMatch ? 'match' : 'different',
                                isMatch ? 'Scan berhasil (Sementara)' : 'Jancode tidak sesuai target!'
                            );

                            if (response.is_complete) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Selesai!',
                                    text: 'Qty terpenuhi. Silakan klik tombol Submit untuk menyimpan data.',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function (xhr) {
                            const data = xhr.responseJSON;
                            Swal.close();
                            if (data && data.error === 'over') {
                                updateCountersFromError(data);
                                addScanLogRow(jancode, lockedTargetJancode, false, 'over', data.message || 'Over Scan! Qty melebihi target');
                                Swal.fire('Over Scan!', data.message, 'warning').then(() => {
                                    if (lockedTargetId) $input.focus();
                                    processQueue();
                                });
                            } else if (data && data.error === 'not_found') {
                                refreshCount();
                                addScanLogRow(jancode, lockedTargetJancode, false, 'different', data.message || 'Barcode tidak cocok dengan Target');
                                // Swal.fire({
                                //     icon: 'error',
                                //     title: 'Tidak Cocok!',
                                //     text: data.message || 'Barcode tidak sesuai target',
                                //     confirmButtonText: 'OK'
                                // }).then(() => {
                                //     if (lockedTargetId) $input.focus();
                                //     processQueue();
                                // });
                            } else {
                                refreshCount();
                                addScanLogRow(jancode, lockedTargetJancode, false, 'different', (data && data.message) ? data.message : 'Terjadi kesalahan scan');
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: data ? data.message : 'Terjadi kesalahan',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    if (lockedTargetId) $input.focus();
                                    processQueue();
                                });
                            }
                        },
                        complete: function () {
                            isScanning = false;
                            if (lockedTargetId && !Swal.isVisible()) $input.focus();
                            processQueue();
                        }
                    });
                }

                // ── Update Counters ────────────────────────────────────────
                function updateCounters(data) {
                    $('#counter-cards').show();

                    const scanned = data.scanned || 0;
                    const pending = scanQueue.length;
                    const unsubmitted = (data.unsubmitted || 0) + pending;
                    const total = data.qty || 0;
                    const totalAll = (data.total_all || (scanned + data.unsubmitted)) + pending;
                    const balance = total - totalAll;

                    $('#count-scanned').text(scanned);
                    $('#count-unsubmitted').text(unsubmitted);
                    $('#count-total').text(total);
                    $('#count-balance').text(balance);

                    if (data.description) {
                        $('#info-box').show();
                        $('#info-desc').text(data.description);
                        $('#info-size').text(data.size);
                        $('#info-color').text(data.color);
                        $('#info-jancode').text(data.jancode || lockedTargetJancode || '-');
                    }

                    updateBalanceStyle(balance, data.is_complete);

                    // Populate existing logs on initial target lock if empty
                    if (data.logs && data.logs.length > 0 && scanLogIndex === 0) {
                        const targetCode = data.jancode || lockedTargetJancode;
                        data.logs.slice().reverse().forEach(function (logItem) {
                            const isSub = (logItem.is_submitted === 'true');
                            const timeStr = logItem.created_at ? new Date(logItem.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : null;
                            addScanLogRow(
                                logItem.jancode,
                                targetCode,
                                true,
                                isSub ? 'submitted' : 'saved',
                                isSub ? 'Scan tersimpan (Disubmit)' : 'Scan sementara (Belum submit)',
                                timeStr
                            );
                        });
                    }
                }

                function updateCountersFromError(data) {
                    $('#counter-cards').show();

                    const scanned = data.scanned || 0;
                    const unsubmitted = data.unsubmitted || 0;
                    const total = data.qty || 0;
                    const totalAll = data.total_all || (scanned + unsubmitted);
                    const balance = total - totalAll;

                    $('#count-scanned').text(scanned);
                    $('#count-unsubmitted').text(unsubmitted);
                    $('#count-total').text(total);
                    $('#count-balance').text(balance);

                    if (data.description) {
                        $('#info-box').show();
                        $('#info-desc').text(data.description);
                        $('#info-size').text(data.size);
                        $('#info-color').text(data.color);
                        $('#info-jancode').text(data.locked_barcode || data.jancode || lockedTargetJancode || '-');
                    }

                    updateBalanceStyle(balance, true);
                }

                function updateBalanceStyle(balance, isComplete) {
                    const $card = $('#card-balance');
                    const $label = $('#label-balance');
                    const $icon = $('#icon-balance');
                    const $banner = $('#status-banner');
                    const $alert = $('#status-alert');

                    $card.removeClass('border-left-danger border-left-warning border-left-danger');

                    if (balance < 0) {
                        $card.addClass('border-left-danger');
                        $label.css('color', '#e74a3b');
                        $icon.css('color', '#e74a3b');
                        $banner.show();
                        $alert.removeClass('alert-success alert-info').addClass('alert-danger')
                            .html('<i class="fas fa-exclamation-triangle mr-1"></i> Over Scan! Melebihi qty master');
                    } else if (balance === 0) {
                        $card.addClass('border-left-success');
                        $label.css('color', '#1cc88a');
                        $icon.css('color', '#1cc88a');
                        $banner.show();
                        $alert.removeClass('alert-danger alert-info').addClass('alert-success')
                            .html('<i class="fas fa-check-circle mr-1"></i> Scan selesai! Qty terpenuhi');
                    } else {
                        $card.addClass('border-left-danger');
                        $label.css('color', '#1cc88a');
                        $icon.css('color', '#1cc88a');
                        $banner.hide();
                    }
                }

                function resetCounters() {
                    $('#count-scanned').text('0');
                    $('#count-unsubmitted').text('0');
                    $('#count-total').text('0');
                    $('#count-balance').text('0');
                    $('#status-banner').hide();
                    $('#card-balance').removeClass('border-left-danger border-left-warning border-left-danger');
                }

                // ── Void Last ──────────────────────────────────────────────
                $('#btn-void').on('click', function () {
                    if (!lockedTargetId) {
                        Swal.fire({ icon: 'warning', title: 'Belum ada scan aktif!', confirmButtonText: 'OK' });
                        return;
                    }

                    Swal.fire({
                        title: 'Void scan terakhir?',
                        text: 'Scan terakhir untuk ' + lockedTargetJancode + ' akan dihapus.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, void!',
                        cancelButtonText: 'Batal'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: '/scanner/void',
                                type: 'DELETE',
                                contentType: 'application/json',
                                data: JSON.stringify({ jancode_id: lockedTargetId }),
                                success: function (response) {
                                    refreshCount();
                                    addScanLogRow(lockedTargetJancode, lockedTargetJancode, false, 'different', 'Scan terakhir berhasil di-void');
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil di-void!',
                                        timer: 1500,
                                        showConfirmButton: false
                                    });
                                },
                                error: function (xhr) {
                                    const data = xhr.responseJSON;
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Gagal void!',
                                        text: data ? data.error : 'Error',
                                        confirmButtonText: 'OK'
                                    });
                                },
                                complete: function () {
                                    $input.focus();
                                }
                            });
                        } else {
                            $input.focus();
                        }
                    });
                });

                // ── Reset Lock ─────────────────────────────────────────────
                $('#btn-reset').on('click', function () {
                    Swal.fire({
                        title: 'Unlock Barcode?',
                        text: 'Sesi scan saat ini akan dibatalkan, Anda dapat scan jancode lain.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e74a3b',
                        cancelButtonColor: '#858796',
                        confirmButtonText: 'Ya, Reset!'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: "{{ route('scanner.reset') }}",
                                type: 'POST',
                                success: function () {
                                    $input.removeAttr('maxlength');
                                    resetCounters();
                                    $('#info-box').hide();
                                    $('#counter-cards').hide();
                                    clearScanLogs();
                                    $('#log-card').hide();
                                    Swal.fire('Direset!', 'Sesi scan berhasil direset.', 'success');
                                },
                                error: function () {
                                    Swal.fire('Error!', 'Gagal mereset sesi.', 'error');
                                },
                                complete: function () {
                                    $input.val('').focus();
                                }
                            });
                        } else {
                            $input.focus();
                        }
                    });
                });

                // ── Submit Scan ────────────────────────────────────────────
                $('#btn-submit').on('click', function () {
                    if (!lockedTargetId) {
                        Swal.fire({ icon: 'warning', title: 'Belum ada scan aktif!', confirmButtonText: 'OK' });
                        return;
                    }

                    const unsubmitted = parseInt($('#count-unsubmitted').text());
                    if (unsubmitted <= 0) {
                        Swal.fire({ icon: 'info', title: 'Tidak ada scan sementara', text: 'Anda belum melakukan scan baru.', confirmButtonText: 'OK' });
                        return;
                    }

                    Swal.fire({
                        title: 'Submit batch scan?',
                        text: unsubmitted + ' scan sementara akan di-submit.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, Submit!',
                        cancelButtonText: 'Batal'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: "{{ route('scanner.submit') }}",
                                type: 'POST',
                                contentType: 'application/json',
                                data: JSON.stringify({ jancode_id: lockedTargetId }),
                                success: function (response) {
                                    updateCounters(response);
                                    addScanLogRow('-', lockedTargetJancode, true, 'submitted', unsubmitted + ' scan sementara berhasil disubmit ke server');
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil!',
                                        text: 'Scan sementara berhasil disubmit.',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        if (response.is_complete) {
                                            lockedTargetId = null;
                                            lockedTargetJancode = null;
                                            $targetSelect.prop('disabled', false).val(null).trigger('change');
                                            $btnUnlock.hide();
                                            $btnLock.show();
                                            $input.prop('disabled', true).val('');
                                            $('#info-box').hide();
                                            $('#counter-cards').hide();
                                        }
                                    });
                                },
                                error: function (xhr) {
                                    const data = xhr.responseJSON;
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Gagal Submit!',
                                        text: data ? data.message : 'Error',
                                        confirmButtonText: 'OK'
                                    });
                                },
                                complete: function () {
                                    $input.focus();
                                }
                            });
                        } else {
                            $input.focus();
                        }
                    });
                });

                // ── Refresh Count (after void / lock) ──────────────────────
                function refreshCount() {
                    if (!lockedTargetId) return;
                    $.ajax({
                        url: '/scanner/count',
                        type: 'GET',
                        data: { jancode_id: lockedTargetId },
                        success: function (data) {
                            updateCounters(data);
                        }
                    });
                }

                // ── Rollback Scan Sementara ────────────────────────────────
                $('#btn-rollback').on('click', function () {
                    if (!lockedTargetId) {
                        Swal.fire({ icon: 'warning', title: 'Belum ada scan aktif!', confirmButtonText: 'OK' });
                        return;
                    }

                    const unsubmitted = parseInt($('#count-unsubmitted').text());
                    if (unsubmitted <= 0) {
                        Swal.fire({ icon: 'info', title: 'Tidak ada data rollback', text: 'Tidak ada scan sementara untuk di-rollback.', confirmButtonText: 'OK' });
                        return;
                    }

                    Swal.fire({
                        title: 'Rollback semua scan sementara?',
                        text: unsubmitted + ' data scan yang belum disubmit akan dihapus permanen!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, hapus!',
                        cancelButtonText: 'Batal'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: "{{ route('scanner.rollback') }}",
                                type: 'DELETE',
                                contentType: 'application/json',
                                data: JSON.stringify({ jancode_id: lockedTargetId }),
                                success: function (response) {
                                    refreshCount();
                                    addScanLogRow('-', lockedTargetJancode, false, 'different', response.message || 'Scan sementara di-rollback');
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil!',
                                        text: response.message,
                                        timer: 1500,
                                        showConfirmButton: false
                                    });
                                },
                                error: function (xhr) {
                                    const data = xhr.responseJSON;
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Gagal Rollback!',
                                        text: data ? data.error : 'Error',
                                        confirmButtonText: 'OK'
                                    });
                                },
                                complete: function () {
                                    $input.focus();
                                }
                            });
                        } else {
                            $input.focus();
                        }
                    });
                });

            });
        </script>

</html>