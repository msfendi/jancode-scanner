<!DOCTYPE html>
<html lang="en">
@include('layout.header')

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
                                <!-- Input -->
                                <div class="col-lg-6">
                                    <label>Jancode :</label>
                                    <div class="input-group">
                                        <input class="form-control form-control-lg" type="text" id="jancode"
                                            name="jancode" autocomplete="off" placeholder="Scan atau ketik jancode..."
                                            maxlength="">
                                        <div class="input-group-append">
                                            <button class="btn btn-success" id="btn-submit" type="button">
                                                <i class="fas fa-check fa-sm mr-1"></i> Submit
                                            </button>
                                            <button class="btn btn-warning" id="btn-rollback" type="button">
                                                <i class="fas fa-undo fa-sm mr-1"></i> Rollback
                                            </button>
                                            <button class="btn btn-danger" id="btn-reset" type="button">
                                                <i class="fas fa-sync-alt fa-sm mr-1"></i> Unlock Barcode
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Scanner otomatis submit setelah selesai scan.
                                        <strong>Void Last</strong> untuk hapus scan terakhir.
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

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            @include('layout.footer')

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script type="text/javascript">
        $(document).ready(function () {

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            // ── Variables ──────────────────────────────────────────────
            let currentJancode = null;
            let isScanning = false;
            let bufferTimer = null;
            const BUFFER_DELAY = 200;
            let scanQueue = []; // Antrean scan

            // ── Auto Focus ─────────────────────────────────────────────
            const $input = $('#jancode');
            $input.focus();
            $input.on('blur', function () {
                setTimeout(function () { $input.focus(); }, 200);
            });

            // ── Input Handling ─────────────────────────────────────────
            $input.on('keydown', function (e) {
                // Enter key — queue immediately
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    clearTimeout(bufferTimer);
                    queueScan();
                    return;
                }
            });

            $input.on('input', function () {
                clearTimeout(bufferTimer);
                bufferTimer = setTimeout(function () {
                    queueScan();
                }, BUFFER_DELAY);
            });

            // ── Queue Scan ─────────────────────────────────────────────
            function queueScan() {
                const jancode = $input.val().trim();
                if (!jancode) return;

                // Langsung kosongkan input agar scan berikutnya bisa masuk
                $input.val('');

                // Optimistic UI untuk respon instan tanpa delay visual
                if (currentJancode === jancode) {
                    let scanned = parseInt($('#count-scanned').text()) || 0;
                    let unsubmitted = parseInt($('#count-unsubmitted').text()) || 0;
                    let total = parseInt($('#count-total').text()) || 0;
                    
                    $('#count-unsubmitted').text(unsubmitted + 1);
                    let balance = total - (scanned + unsubmitted + 1);
                    $('#count-balance').text(balance);
                    updateBalanceStyle(balance, false);
                }

                scanQueue.push(jancode);
                processQueue();
            }

            // ── Process Queue ──────────────────────────────────────────
            function processQueue() {
                if (isScanning || scanQueue.length === 0) return;

                const jancode = scanQueue.shift();
                isScanning = true;

                // Track if this is a new jancode
                if (jancode !== currentJancode) {
                    currentJancode = jancode;
                    resetCounters();
                }

                $.ajax({
                    url: '/scanner/scan',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ jancode: jancode }),
                    success: function (response) {
                        updateCounters(response);

                        if (response.is_complete) {
                            scanQueue = []; // Hapus antrean tersisa karena limit sudah tercapai
                            $input.removeAttr('maxlength');
                            Swal.fire({
                                icon: 'success',
                                title: 'Selesai!',
                                text: 'Qty terpenuhi untuk jancode ' + jancode,
                                confirmButtonText: 'OK'
                            });
                        } else {
                            if (currentJancode) {
                                $input.attr('maxlength', currentJancode.length);
                            }
                        }
                    },
                    error: function (xhr) {
                        scanQueue = []; // Bersihkan antrean jika ada error agar tidak diteruskan

                        const data = xhr.responseJSON;
                        if (data && data.error === 'locked') {
                            // Revert to locked barcode to show correct info
                            currentJancode = data.locked_barcode;
                            if (currentJancode) {
                                $input.attr('maxlength', currentJancode.length);
                            }
                            updateCountersFromError(data);
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: data.message,
                                confirmButtonText: 'OK'
                            });
                        } else if (data && data.error === 'over') {
                            $input.removeAttr('maxlength');
                            updateCountersFromError(data);
                            Swal.fire({
                                icon: 'warning',
                                title: 'Over Scan!',
                                text: data.message,
                                confirmButtonText: 'OK'
                            });
                        } else if (data && data.error === 'not_found') {
                            currentJancode = null;
                            $input.removeAttr('maxlength');
                            Swal.fire({
                                icon: 'error',
                                title: 'Tidak Ditemukan!',
                                text: data.message || 'Jancode tidak ditemukan',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: data ? data.message : 'Terjadi kesalahan',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    complete: function () {
                        isScanning = false;
                        $input.focus();
                        processQueue(); // Lanjut ke antrean berikutnya jika ada
                    }
                });
            }

            // ── Update Counters ────────────────────────────────────────
            function updateCounters(data) {
                $('#counter-cards').show();

                const scanned = data.scanned || 0;
                // Tambahkan sisa antrean ke unsubmitted agar sinkron dengan Optimistic UI
                const pending = scanQueue.length;
                const unsubmitted = (data.unsubmitted || 0) + pending;
                const total = data.qty || 0;
                const totalAll = (data.total_all || (scanned + data.unsubmitted)) + pending;
                const balance = total - totalAll;

                $('#count-scanned').text(scanned);
                $('#count-unsubmitted').text(unsubmitted);
                $('#count-total').text(total);
                $('#count-balance').text(balance);

                // Show product info from SSE or first scan
                if (data.description) {
                    $('#info-box').show();
                    $('#info-desc').text(data.description);
                    $('#info-size').text(data.size);
                    $('#info-color').text(data.color);
                    $('#info-jancode').text(currentJancode);
                }

                updateBalanceStyle(balance, data.is_complete);
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
                    $('#info-jancode').text(data.locked_barcode || currentJancode);
                }

                updateBalanceStyle(balance, true);
            }

            function updateBalanceStyle(balance, isComplete) {
                const $card = $('#card-balance');
                const $label = $('#label-balance');
                const $icon = $('#icon-balance');
                const $banner = $('#status-banner');
                const $alert = $('#status-alert');

                // Remove previous border classes
                $card.removeClass('border-left-danger border-left-warning border-left-danger');

                if (balance < 0) {
                    // Over scan
                    $card.addClass('border-left-danger');
                    $label.css('color', '#e74a3b');
                    $icon.css('color', '#e74a3b');
                    $banner.show();
                    $alert.removeClass('alert-success alert-info').addClass('alert-danger')
                        .html('<i class="fas fa-exclamation-triangle mr-1"></i> Over Scan! Melebihi qty master');
                } else if (balance === 0) {
                    // Complete
                    $card.addClass('border-left-success');
                    $label.css('color', '#1cc88a');
                    $icon.css('color', '#1cc88a');
                    $banner.show();
                    $alert.removeClass('alert-danger alert-info').addClass('alert-success')
                        .html('<i class="fas fa-check-circle mr-1"></i> Scan selesai! Qty terpenuhi');
                } else {
                    // Still scanning
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
                if (!currentJancode) {
                    Swal.fire({ icon: 'warning', title: 'Belum ada scan aktif!', confirmButtonText: 'OK' });
                    return;
                }

                Swal.fire({
                    title: 'Void scan terakhir?',
                    text: 'Scan terakhir untuk ' + currentJancode + ' akan dihapus.',
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
                            data: JSON.stringify({ jancode: currentJancode }),
                            success: function (response) {
                                // Re-fetch current count after void
                                refreshCount();
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
                                currentJancode = null;
                                $input.removeAttr('maxlength');
                                resetCounters();
                                $('#info-box').hide();
                                $('#counter-cards').hide();
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
                if (!currentJancode) {
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
                            data: JSON.stringify({ jancode: currentJancode }),
                            success: function (response) {
                                updateCounters(response);
                                if (response.is_complete) {
                                    $input.removeAttr('maxlength');
                                } else if (currentJancode) {
                                    $input.attr('maxlength', currentJancode.length);
                                }
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: 'Scan sementara berhasil disubmit.',
                                    timer: 1500,
                                    showConfirmButton: false
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

            // ── Refresh Count (after void) ─────────────────────────────
            function refreshCount() {
                $.ajax({
                    url: '/scanner/count',
                    type: 'GET',
                    data: { jancode: currentJancode },
                    success: function (data) {
                        updateCounters(data);
                    }
                });
            }

            // ── Rollback Scan Sementara ────────────────────────────────
            $('#btn-rollback').on('click', function () {
                if (!currentJancode) {
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
                            data: JSON.stringify({ jancode: currentJancode }),
                            success: function (response) {
                                refreshCount();
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