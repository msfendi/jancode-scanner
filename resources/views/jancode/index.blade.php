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
                                <i class="fas fa-file-import text-primary mr-1"></i> Import Packing List
                            </h1>
                            <p class="mb-0 text-muted small">
                                Langkah <strong>2</strong>: upload file Excel packing list. Setiap sheet disimpan
                                sebagai <strong>session terpisah</strong> (1 sheet = 1 session).
                            </p>
                        </div>
                        <a href="{{ route('jancode.packingLists') }}" class="btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-tasks fa-sm mr-1"></i> Manage Packing List
                        </a>
                    </div>

                    @include('layout.workflow-steps', ['current' => 'import'])

                    @include('layout.session-banner', ['currentSession' => $currentSession])

                    @php $canImport = $currentSession && $currentSession->isOpen(); @endphp

                    @if (!$canImport)
                        <!-- Gate: session belum aktif / ditutup -->
                        <div class="card shadow mb-4 border-left-warning">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-lock fa-2x text-warning mb-3"></i>
                                <h5 class="font-weight-bold text-gray-800">Import terkunci</h5>
                                <p class="text-muted mb-3">
                                    @if (!$currentSession)
                                        Mulai session terlebih dahulu sebagai induk packing list.
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

                    <!-- Penjelasan alur -->
                    <div class="alert alert-info border-left-info shadow-sm mb-3 py-2">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle fa-lg mr-3 text-info"></i>
                            <div class="small">
                                <strong>Cara kerja:</strong>
                                Upload file &rarr; sistem membaca seluruh sheet &rarr; setiap sheet menjadi
                                <strong>session sendiri</strong> (1 sheet = 1 session, bukan revisi) &rarr;
                                hanya baris <strong>SOLID</strong> yang masuk, baris lain disimpan sebagai
                                <strong>broken</strong> &rarr; setelah disimpan, packing list siap di-scan.
                            </div>
                        </div>
                    </div>

                    <!-- 1. Upload Card -->
                    <div class="card shadow mb-4 {{ $canImport ? '' : 'd-none' }}" id="aiUploadCard">
                        <div class="card-header py-2">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-cloud-upload-alt mr-1"></i> 1. Upload File Packing List
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="aiUploadForm" enctype="multipart/form-data">
                                <!-- Upload State -->
                                <div id="aiUploadSection">
                                    <div id="importDropzone" class="import-dropzone text-center rounded p-4"
                                        role="button" tabindex="0">
                                        <i class="fas fa-file-excel fa-2x text-success mb-2"></i>
                                        <div class="font-weight-bold text-gray-800">Tarik &amp; lepas file di sini</div>
                                        <div class="text-muted small mb-2">atau klik untuk memilih file Excel</div>
                                        <span class="btn btn-sm btn-primary">
                                            <i class="fas fa-folder-open mr-1"></i> Pilih File Excel
                                        </span>
                                        <div class="small text-muted mt-2">Format didukung: .xlsx / .xls (maks 15MB)</div>
                                    </div>
                                    <input type="file" name="file" id="aiExcelFile" accept=".xlsx,.xls" class="d-none">

                                    <div id="aiFileSelected" class="d-none align-items-center justify-content-between border rounded p-2 bg-light">
                                        <div class="text-truncate">
                                            <i class="fas fa-file-excel text-success mr-1"></i>
                                            <span class="font-weight-bold small" id="aiSelectedName">-</span>
                                        </div>
                                        <div class="text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnChooseAnother">
                                                Ganti File
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-primary font-weight-bold" id="btnProcessAi">
                                                <i class="fas fa-magic mr-1"></i> Mulai Import
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Processing State -->
                                <div id="aiLoadingSection" class="text-center py-4 d-none">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <div class="mt-2 font-weight-bold text-gray-800">Membaca &amp; menganalisis file Excel...</div>
                                    <div class="text-muted small">Mendeteksi revisi &amp; menyaring baris SOLID. Mohon tunggu.</div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 2. Preview & Konfirmasi (muncul setelah file diproses) -->
                    <div class="card shadow mb-4 d-none" id="aiPreviewCard">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-clipboard-check mr-1"></i> 2. Preview &amp; Konfirmasi
                            </h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAiReset">
                                    <i class="fas fa-undo mr-1"></i> Upload Ulang
                                </button>
                                <button type="button" class="btn btn-sm btn-success font-weight-bold d-none" id="btnAiConfirmSave">
                                    <i class="fas fa-cloud-upload-alt mr-1"></i> Simpan Packing List
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Sheet (Packing List) Selector -->
                            <div class="card border-left-dark shadow-sm mb-3">
                                <div class="card-body py-2">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                                        <div class="mb-2 mb-md-0">
                                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">
                                                <i class="fas fa-layer-group mr-1"></i> Packing List Terdeteksi
                                            </div>
                                            <div id="aiSheetTabs" class="btn-group flex-wrap" role="group" aria-label="Pilih packing list"></div>
                                        </div>
                                        <div class="text-right" id="aiSheetInfo"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Warning banner if mismatch -->
                            <div id="cartonMismatchAlert" class="alert alert-warning border-left-warning d-none py-2">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>Peringatan Verifikasi Total Carton:</strong>
                                Hasil ekstraksi tidak sepenuhnya sinkron dengan hitungan carton deterministik. Harap tinjau dengan seksama sebelum konfirmasi.
                            </div>

                            <!-- Rencana session per sheet (setiap sheet = 1 session) -->
                            <div id="aiRoutingAlert" class="alert alert-info border-left-info py-2 d-none">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    <i class="fas fa-sitemap mr-1"></i> Pengelompokan Session
                                </div>
                                <div class="small mb-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Setiap <strong>sheet</strong> di file ini diimport sebagai
                                    <strong>session terpisah</strong> (bukan sebagai revisi).
                                </div>
                                <div id="aiRoutingList" class="small"></div>
                            </div>

                            <!-- Summary Cards -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <div class="card border-left-primary shadow-sm h-100 py-2">
                                        <div class="card-body py-1">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-xs font-weight-bold text-primary text-uppercase">Buyer / Country</span>
                                                <span id="previewEngineMode"></span>
                                            </div>
                                            <div class="font-weight-bold text-gray-800" id="previewBuyerCountry">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-left-info shadow-sm h-100 py-2">
                                        <div class="card-body py-1">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Style / OCF</div>
                                            <div class="font-weight-bold text-gray-800" id="previewStyleOcf">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-left-success shadow-sm h-100 py-2">
                                        <div class="card-body py-1">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Valid Import (Carton)</div>
                                            <div class="font-weight-bold text-gray-800">
                                                <span id="previewTotalItems">0</span> Baris (<span id="previewTotalCartons">0</span> Carton)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-left-secondary shadow-sm h-100 py-2">
                                        <div class="card-body py-1">
                                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Baris Dilewati (Skipped)</div>
                                            <div class="font-weight-bold text-gray-800">
                                                <span id="previewSkippedCount">0</span> Baris
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabs: Valid vs Skipped -->
                            <ul class="nav nav-tabs font-weight-bold small mb-3" id="aiTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active text-success py-2" id="valid-tab" data-toggle="tab" href="#validRowsTab" role="tab">
                                        <i class="fas fa-check-circle mr-1"></i> Baris Valid (<span id="badgeValidCount">0</span>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link text-secondary py-2" id="skipped-tab" data-toggle="tab" href="#skippedRowsTab" role="tab">
                                        <i class="fas fa-times-circle mr-1"></i> Baris Dilewati (<span id="badgeSkippedCount">0</span>)
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content" id="aiTabContent">
                                <!-- Tab 1: Valid Rows -->
                                <div class="tab-pane fade show active" id="validRowsTab" role="tabpanel">
                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                        <table class="table table-bordered table-sm table-hover mb-0" id="tablePreviewValid">
                                            <thead class="thead-light sticky-top">
                                                <tr>
                                                    <th width="45">Row</th>
                                                    <th width="90">Carton No</th>
                                                    <th>Color</th>
                                                    <th>JAN Code</th>
                                                    <th width="65">Size</th>
                                                    <th width="80" class="text-right">Karton</th>
                                                    <th width="75" class="text-right">Pcs/Ctn</th>
                                                    <th width="85" class="text-right">Total Pcs</th>
                                                    <th width="105">NW / GW (kg)</th>
                                                    <th width="115">Dimensi / CBM</th>
                                                    <th width="80" class="text-center">Status</th>
                                                    <th width="45" class="text-center">Hapus</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyPreviewValid">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Tab 2: Skipped Rows -->
                                <div class="tab-pane fade" id="skippedRowsTab" role="tabpanel">
                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                        <table class="table table-bordered table-sm mb-0">
                                            <thead class="thead-light sticky-top">
                                                <tr>
                                                    <th width="85">Row Excel</th>
                                                    <th width="130">Alasan</th>
                                                    <th>Color</th>
                                                    <th>JAN Code</th>
                                                    <th>Size</th>
                                                    <th width="85" class="text-right">Carton</th>
                                                    <th>Catatan / Keterangan</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyPreviewSkipped">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @include('layout.footer')

        </div>
    </div>

    <!-- Page level plugins -->
    <script src="{{ asset('vendor/sweetalert/sweetalert.all.js') }}"></script>

    <style>
        .import-dropzone {
            border: 2px dashed #c2cbe2;
            background-color: #f8f9fc;
            cursor: pointer;
            transition: all .15s ease-in-out;
        }
        .import-dropzone:hover,
        .import-dropzone.dragover {
            border-color: #4e73df;
            background-color: #eef2ff;
        }
    </style>

    <script type="text/javascript">
        $(function () {
            // --- Ajax Setup ---
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Apakah session aktif mengizinkan import?
            const canImport = @json($canImport);

            // --- Dropzone & pemilihan file ---
            const dz = document.getElementById('importDropzone');
            const $fileInput = $('#aiExcelFile');

            function showSelectedFile(name) {
                $('#aiSelectedName').text(name || '-');
                $('#importDropzone').addClass('d-none');
                $('#aiFileSelected').removeClass('d-none').addClass('d-flex');
            }

            function resetFilePicker() {
                $fileInput.val('');
                $('#aiFileSelected').addClass('d-none').removeClass('d-flex');
                $('#importDropzone').removeClass('d-none');
            }

            dz.addEventListener('click', function () { $fileInput.trigger('click'); });
            dz.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $fileInput.trigger('click'); }
            });

            ['dragenter', 'dragover'].forEach(function (evt) {
                dz.addEventListener(evt, function (e) { e.preventDefault(); dz.classList.add('dragover'); });
            });
            ['dragleave', 'drop'].forEach(function (evt) {
                dz.addEventListener(evt, function (e) { e.preventDefault(); dz.classList.remove('dragover'); });
            });
            dz.addEventListener('drop', function (e) {
                const files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) {
                    $fileInput[0].files = files;
                    $fileInput.trigger('change');
                }
            });

            $fileInput.on('change', function () {
                const fileName = $(this).val().split('\\').pop();
                if (fileName) { showSelectedFile(fileName); }
            });

            $('#btnChooseAnother').on('click', function () {
                resetFilePicker();
                $fileInput.trigger('click');
            });

            // ==========================================
            // --- AI Import Interactive Workflow ---
            // ==========================================
            let aiParsedData = null;          // payload mentah dari server { sheets: [...] }
            let aiSheets = [];                // daftar sheet (packing list) pada file
            let aiActiveSheet = 0;            // index sheet yang sedang ditampilkan
            let aiOriginalFilename = null;
            let aiRouting = [];               // rencana pengelompokan session per group

            function aiSheet() {
                return (aiSheets && aiSheets[aiActiveSheet]) ? aiSheets[aiActiveSheet] : null;
            }

            function showPreviewMode() {
                $('#aiUploadCard').addClass('d-none');
                $('#aiPreviewCard').removeClass('d-none');
                $('html, body').animate({ scrollTop: $('#aiPreviewCard').offset().top - 80 }, 250);
            }

            $('#btnAiReset').click(function () {
                $('#aiPreviewCard').addClass('d-none');
                $('#aiLoadingSection').addClass('d-none');
                if (canImport) {
                    $('#aiUploadCard').removeClass('d-none');
                }
                $('#aiUploadSection').removeClass('d-none');
                $('#btnAiConfirmSave').addClass('d-none');
                resetFilePicker();
                aiParsedData = null;
                aiSheets = [];
                aiRouting = [];
                aiActiveSheet = 0;
                $('#aiRoutingAlert').addClass('d-none');
            });

            $('#aiUploadForm').on('submit', function (e) {
                e.preventDefault();

                if (!$fileInput.val()) {
                    Swal.fire('Perhatian', 'Silakan pilih file Excel terlebih dahulu.', 'warning');
                    return;
                }

                const formData = new FormData(this);
                $('#aiUploadSection').addClass('d-none');
                $('#aiPreviewCard').addClass('d-none');
                $('#aiLoadingSection').removeClass('d-none');

                $.ajax({
                    url: "{{ route('jancode.aiPreview') }}",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (res) {
                        $('#aiLoadingSection').addClass('d-none');
                        if (!res.success) {
                            Swal.fire('Error', res.message || 'Gagal memproses file.', 'error');
                            $('#aiUploadSection').removeClass('d-none');
                            return;
                        }

                        aiParsedData = res.data;
                        aiOriginalFilename = res.original_filename || null;
                        aiSheets = (res.data && res.data.sheets) ? res.data.sheets : [];
                        aiRouting = res.routing || [];
                        aiActiveSheet = 0;
                        renderAiPreview();
                    },
                    error: function (xhr) {
                        $('#aiLoadingSection').addClass('d-none');
                        $('#aiUploadSection').removeClass('d-none');

                        const res = xhr.responseJSON || {};

                        // Kombinasi ini sudah dimiliki session lain -> tawarkan
                        // pindah ke session tersebut (buka kembali bila ditutup).
                        if (res.error === 'session_exists' && res.session) {
                            Swal.fire({
                                title: 'Session sudah ada',
                                html: `Kombinasi <strong>${res.session.group_label || '-'}</strong> sudah ada pada session `
                                    + `<strong>${res.session.code}</strong> <span class="badge badge-light border">${(res.session.status || '').toUpperCase()}</span>.<br>`
                                    + `<small class="text-muted">Gunakan session itu agar data tidak tercampur dengan session lain.</small>`,
                                icon: 'info',
                                showCancelButton: true,
                                confirmButtonText: res.session.is_open ? 'Gunakan Session' : 'Buka Kembali &amp; Gunakan',
                                cancelButtonText: 'Batal',
                            }).then(function (r) {
                                if (!r.isConfirmed) return;

                                $.ajax({
                                    url: res.reopen_url,
                                    type: 'POST',
                                    headers: { 'Accept': 'application/json' },
                                }).done(function (rr) {
                                    if (rr && rr.code) {
                                        $('.session-pill').html('<i class="fas fa-layer-group mr-1"></i>' + rr.code);
                                    }
                                    if (rr && rr.group_label) {
                                        $('.session-banner .font-weight-bold').first().text(rr.group_label);
                                    }
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Session aktif',
                                        text: (rr && rr.message) || 'Session dipilih.',
                                        timer: 1600,
                                        showConfirmButton: false,
                                    }).then(function () {
                                        $('#aiUploadForm').trigger('submit');
                                    });
                                }).fail(function () {
                                    Swal.fire('Gagal', 'Tidak bisa membuka session tersebut.', 'error');
                                });
                            });
                            return;
                        }

                        const msg = res.message || 'Terjadi kesalahan saat memproses Excel.';
                        Swal.fire('Proses Gagal', msg, 'error');
                    }
                });
            });

            function renderAiPreview() {
                if (!aiSheets.length) {
                    Swal.fire('Error', 'Tidak ada sheet packing list yang terdeteksi.', 'error');
                    $('#btnAiReset').trigger('click');
                    return;
                }

                renderRoutingBlock();
                renderSheetTabs();
                renderActiveSheet();

                $('#btnAiConfirmSave').removeClass('d-none');
                showPreviewMode();
            }

            // Tampilkan rencana pengelompokan: group mana masuk session mana.
            function renderRoutingBlock() {
                if (!aiRouting || !aiRouting.length) {
                    $('#aiRoutingAlert').addClass('d-none');
                    return;
                }

                let html = '<ul class="mb-0 pl-3">';
                aiRouting.forEach(function (r) {
                    const docTypes = r.doc_types ? ` <span class="badge badge-light border">${r.doc_types}</span>` : '';
                    const revLabel = Number(r.next_revision) === 1 ? 'Original' : 'Rev.' + r.next_revision;
                    let target;
                    if (r.is_new) {
                        target = '<span class="badge badge-success">Session baru</span>';
                    } else {
                        const status = (r.session_status || '').toUpperCase();
                        const badge = status === 'OPEN'
                            ? '<span class="badge badge-success ml-1">OPEN</span>'
                            : '<span class="badge badge-secondary ml-1">CLOSED &rarr; dibuka</span>';
                        target = `<strong>${r.session_code}</strong>${badge}`;
                    }
                    html += `<li class="mb-2">
                        <strong>${r.sheets || 'Sheet'}</strong>${docTypes}
                        <span class="text-muted">· ${r.group_label}</span><br>
                        &rarr; ${target} <span class="text-muted">(revisi ${revLabel}, ${r.total_items} baris / ${r.total_carton} carton)</span>
                    </li>`;
                });
                html += '</ul>';

                $('#aiRoutingList').html(html);
                $('#aiRoutingAlert').removeClass('d-none');
            }

            function renderSheetTabs() {
                let html = '';
                aiSheets.forEach(function (sheet, idx) {
                    const active = idx === aiActiveSheet;
                    const docType = (sheet.doc_type || 'proforma').toLowerCase();
                    const badge = docType === 'actual'
                        ? '<span class="badge badge-warning text-dark ml-1">ACTUAL</span>'
                        : '<span class="badge badge-info ml-1">PROFORMA</span>';
                    const rev = sheet.next_revision ? `<small class="text-muted ml-1">${Number(sheet.next_revision) === 1 ? 'Original' : 'Rev.' + sheet.next_revision}</small>` : '';
                    html += `<button type="button" class="btn btn-sm ${active ? 'btn-primary' : 'btn-outline-secondary'} mb-1 mr-1 btn-ai-sheet" data-index="${idx}">
                        <i class="fas fa-file-alt mr-1"></i> ${sheet.sheet_name || ('Sheet ' + (idx + 1))} ${badge}${rev}
                    </button>`;
                });
                $('#aiSheetTabs').html(html);
            }

            function renderActiveSheet() {
                const sheet = aiSheet();
                if (!sheet) return;

                const header = sheet.header || {};
                $('#previewBuyerCountry').text((header.buyer || '-') + ' / ' + (header.country || '-'));
                $('#previewStyleOcf').text((header.style || '-') + ' / ' + (header.ocf || '-'));
                $('#previewEngineMode').html('<span class="badge badge-info"><i class="fas fa-cogs mr-1"></i> Rule Engine</span>');

                const info = [];
                info.push('<div class="small text-muted">Group: <strong>' + (sheet.group_label || '-') + '</strong></div>');
                if (sheet.target_is_new) {
                    info.push('<div class="small text-success font-weight-bold"><i class="fas fa-plus-circle mr-1"></i> Session baru akan dibuat</div>');
                } else if (sheet.target_session_code) {
                    info.push('<div class="small text-primary font-weight-bold"><i class="fas fa-sitemap mr-1"></i> Masuk session: ' + sheet.target_session_code + '</div>');
                }
                if (sheet.active_batch_no) {
                    info.push('<div class="small text-warning font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i> Menggantikan batch aktif: ' + sheet.active_batch_no + '</div>');
                } else {
                    info.push('<div class="small text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Packing list baru (belum ada revisi lain)</div>');
                }
                info.push('<div class="small text-muted">File: <strong>' + (aiOriginalFilename || '-') + '</strong></div>');
                $('#aiSheetInfo').html(info.join(''));

                updateSummaryCounters();
                renderValidRows();
                renderSkippedRows(sheet.skipped || []);
            }

            function updateSummaryCounters() {
                const sheet = aiSheet();
                if (!sheet) return;
                const items = sheet.items || [];
                const skipped = sheet.skipped || [];

                let totalCarton = 0;
                items.forEach(function (it) {
                    totalCarton += parseInt(it.packing_list_qty_carton) || 0;
                });

                $('#previewTotalItems').text(items.length);
                $('#previewTotalCartons').text(totalCarton);
                $('#previewSkippedCount').text(skipped.length);
                $('#badgeValidCount').text(items.length);
                $('#badgeSkippedCount').text(skipped.length);
            }

            function renderValidRows() {
                const sheet = aiSheet();
                const items = (sheet && sheet.items) ? sheet.items : [];
                let html = '';
                if (!items || items.length === 0) {
                    html = '<tr><td colspan="12" class="text-center text-muted py-3">Tidak ada baris valid pada packing list ini.</td></tr>';
                } else {
                    items.forEach(function (item, idx) {
                        const statusBadge = item.is_duplicate
                            ? '<span class="badge badge-warning text-dark"><i class="fas fa-sync-alt mr-1"></i> Update</span>'
                            : '<span class="badge badge-success"><i class="fas fa-plus mr-1"></i> Baru</span>';

                        const pcsPerCarton = parseInt(item.qty_per_carton) || 0;
                        const packingCarton = parseInt(item.packing_list_qty_carton) || 0;
                        const totalPcs = item.total_qty_pcs || (packingCarton * pcsPerCarton);

                        const cartonNo = item.carton_no_from
                            ? (item.carton_no_to && item.carton_no_to !== item.carton_no_from
                                ? `${item.carton_no_from} - ${item.carton_no_to}`
                                : item.carton_no_from)
                            : '-';
                        const nwGw = (item.nw_per_carton != null || item.gw_per_carton != null)
                            ? `${item.nw_per_carton != null ? item.nw_per_carton : '-'} / ${item.gw_per_carton != null ? item.gw_per_carton : '-'}`
                            : '-';
                        const dimCbm = (item.measurement_cm || item.cbm != null)
                            ? `${item.measurement_cm || '-'}${item.cbm != null ? ' (' + item.cbm + ')' : ''}`
                            : '-';

                        html += `<tr data-index="${idx}">
                            <td class="align-middle text-muted">${item.source_row}</td>
                            <td class="align-middle font-weight-bold text-dark">${cartonNo}</td>
                            <td class="align-middle font-weight-bold">${item.color}</td>
                            <td class="align-middle font-mono">${item.jan_code}</td>
                            <td class="align-middle"><span class="badge badge-primary px-2">${item.size}</span></td>
                            <td class="align-middle text-right font-weight-bold text-primary">${packingCarton}</td>
                            <td class="align-middle text-right font-weight-bold text-dark">${pcsPerCarton}</td>
                            <td class="align-middle text-right font-weight-bold text-success">${totalPcs.toLocaleString()}</td>
                            <td class="align-middle small text-muted">${nwGw}</td>
                            <td class="align-middle small text-muted">${dimCbm}</td>
                            <td class="align-middle text-center">${statusBadge}</td>
                            <td class="align-middle text-center">
                                <button type="button" class="btn btn-outline-danger btn-xs btn-remove-preview-row" data-index="${idx}" title="Hapus baris ini">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>`;
                    });
                }
                $('#tbodyPreviewValid').html(html);
            }

            function renderSkippedRows(skippedList) {
                const reasonLabels = {
                    'ASSORT': '<span class="badge badge-secondary">ASSORT</span>',
                    'MERGED_CARTON': '<span class="badge badge-warning text-dark">CARTON MERGE</span>',
                    'SUMMARY_ROW': '<span class="badge badge-light">SUMMARY ROW</span>',
                    'NO_JAN': '<span class="badge badge-danger">NO JAN (13-digit)</span>',
                    'NO_SIZE': '<span class="badge badge-danger">NO SIZE</span>',
                    'MULTI_SIZE': '<span class="badge badge-danger">MULTI SIZE</span>',
                    'NO_CARTON': '<span class="badge badge-danger">NO CARTON QTY</span>',
                    'OTHER': '<span class="badge badge-secondary">OTHER</span>'
                };

                const reasonDescs = {
                    'ASSORT': 'Baris ASSORT PACK (satu carton banyak item / assorting)',
                    'MERGED_CARTON': 'Carton dipakai bersama beberapa baris (cell merge vertikal)',
                    'SUMMARY_ROW': 'Baris ringkasan (TOTAL, QTY, GRAND TOTAL)',
                    'NO_JAN': 'Tidak memiliki barcode JAN 13 digit yang valid',
                    'NO_SIZE': 'Tidak ada size yang terisi di kolom breakdown size',
                    'MULTI_SIZE': 'Lebih dari satu size terisi pada satu baris',
                    'NO_CARTON': 'Nilai TOTAL CARTON kosong atau tidak valid',
                    'OTHER': 'Baris tidak memenuhi kriteria SOLID'
                };

                let html = '';
                if (skippedList.length === 0) {
                    html = '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada baris yang dilewati.</td></tr>';
                } else {
                    skippedList.forEach(function (sk) {
                        const rowDisplay = sk.carton_merged
                            ? `<span class="badge badge-warning text-dark font-weight-bold">${sk.source_row} (Merge)</span>`
                            : `<span class="font-weight-bold text-muted">${sk.source_row}</span>`;
                        const janDisplay = sk.jan_code ? `<code>${sk.jan_code}</code>` : '-';
                        const colorDisplay = sk.color ? `<span class="font-weight-bold text-dark">${sk.color}</span>` : '-';
                        const sizeDisplay = sk.size ? `<span class="badge badge-secondary px-2">${sk.size}</span>` : '-';
                        const cartonDisplay = sk.total_carton !== null ? `<span class="font-weight-bold text-danger">${sk.total_carton}</span>` : '-';
                        const noteDisplay = sk.notes || sk.reason_label || reasonDescs[sk.reason] || '-';

                        html += `<tr>
                            <td class="align-middle">${rowDisplay}</td>
                            <td class="align-middle">${reasonLabels[sk.reason] || sk.reason}</td>
                            <td class="align-middle">${colorDisplay}</td>
                            <td class="align-middle font-mono small">${janDisplay}</td>
                            <td class="align-middle small">${sizeDisplay}</td>
                            <td class="align-middle text-right">${cartonDisplay}</td>
                            <td class="align-middle text-muted small">${noteDisplay}</td>
                        </tr>`;
                    });
                }
                $('#tbodyPreviewSkipped').html(html);
            }

            // Pindah packing list (sheet) pada preview
            $(document).on('click', '.btn-ai-sheet', function () {
                aiActiveSheet = parseInt($(this).data('index')) || 0;
                renderSheetTabs();
                renderActiveSheet();
            });

            // Hapus baris preview
            $(document).on('click', '.btn-remove-preview-row', function () {
                const idx = $(this).data('index');
                const sheet = aiSheet();
                if (sheet && sheet.items && sheet.items[idx]) {
                    sheet.items.splice(idx, 1);
                    renderValidRows();
                    updateSummaryCounters();
                }
            });

            // Konfirmasi & Simpan Packing List
            $('#btnAiConfirmSave').click(function () {
                const totalItems = aiSheets.reduce(function (sum, s) {
                    return sum + ((s.items && s.items.length) ? s.items.length : 0);
                }, 0);

                if (totalItems === 0) {
                    Swal.fire('Perhatian', 'Tidak ada baris data untuk diimport.', 'warning');
                    return;
                }

                Swal.fire({
                    title: 'Konfirmasi Import Packing List',
                    html: `Apakah Anda yakin ingin menyimpan <strong>${aiSheets.length}</strong> packing list (${totalItems} baris jancode) ke sistem?<br><small class="text-muted">Setiap packing list masuk ke session sesuai kombinasi style / OCF / destination / warehouse, disimpan sebagai revisi; revisi terbaru menjadi ACTIVE.</small>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Simpan',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const btn = $(this);
                        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

                        $.ajax({
                            url: "{{ route('jancode.aiConfirm') }}",
                            type: "POST",
                            contentType: "application/json",
                            data: JSON.stringify({
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                sheets: aiSheets,
                                original_filename: aiOriginalFilename,
                            }),
                            success: function (res) {
                                btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt mr-1"></i> Simpan Packing List');
                                if (res.success) {
                                    let successMsg = res.message;
                                    if (res.batches && res.batches.length) {
                                        successMsg += '<hr class="my-2"><div class="text-left small">';
                                        res.batches.forEach(function (b) {
                                            successMsg += `<div class="mb-1"><strong>${b.batch_number}</strong> &middot; <span class="text-primary">${b.session_code || ''}</span> &middot; ${b.group_label} &middot; <span class="badge badge-primary">${(b.doc_type || '').toUpperCase()}</span> ${b.revision_label || ('Rev.' + b.revision_no)} &middot; ${b.imported} baris`;
                                            if (b.skipped > 0) { successMsg += ` &middot; <span class="text-warning">${b.skipped} broken</span>`; }
                                            successMsg += '</div>';
                                        });
                                        successMsg += '</div>';
                                    }
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil!',
                                        html: successMsg,
                                    }).then(() => {
                                        $('#btnAiReset').trigger('click');
                                    });
                                } else {
                                    Swal.fire('Gagal!', res.message || 'Terjadi kesalahan.', 'error');
                                }
                            },
                            error: function (xhr) {
                                btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt mr-1"></i> Simpan Packing List');
                                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Terjadi kegagalan saat menyimpan data.';
                                Swal.fire('Gagal!', msg, 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>

</body>
</html>
