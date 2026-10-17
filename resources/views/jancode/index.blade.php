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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="fas fa-file-import text-primary mr-1"></i> Import Packing List
                            </h1>
                            <p class="mb-0 text-muted small">
                                Langkah <strong>1</strong>: upload file Excel packing list. Setiap sheet disimpan
                                sebagai satu <strong>revisi</strong>.
                            </p>
                        </div>
                        <a href="{{ route('jancode.packingLists') }}" class="btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-tasks fa-sm mr-1"></i> Manage Packing List
                        </a>
                    </div>

                    @include('layout.workflow-steps', ['current' => 'import'])

                    <!-- Penjelasan alur -->
                    <div class="alert alert-info border-left-info shadow-sm mb-4">
                        <div class="d-flex">
                            <i class="fas fa-info-circle fa-2x mr-3 text-info"></i>
                            <div class="small">
                                <strong>Bagaimana cara kerjanya?</strong>
                                <ol class="mb-0 pl-3 mt-1">
                                    <li>Upload file packing list <code>.xlsx / .xls</code> (PROFORMA atau ACTUAL).</li>
                                    <li>Sistem membaca seluruh sheet, mendeteksi revisi, dan menyaring hanya baris <strong>SOLID</strong>.</li>
                                    <li>Baris yang tidak valid (assort / carton merge / total) dipisahkan sebagai <strong>baris broken</strong>.</li>
                                    <li>Setelah disimpan, revisi terbaru otomatis menjadi <strong>aktif</strong> dan siap di-scan.</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-cloud-upload-alt mr-1"></i> 1. Upload File Packing List
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="importDropzone" class="import-dropzone text-center rounded p-5 mb-3"
                                role="button" tabindex="0"
                                data-toggle="modal" data-target="#aiImportModal">
                                <i class="fas fa-file-excel fa-3x text-success mb-3"></i>
                                <h5 class="font-weight-bold text-gray-800 mb-1">Tarik &amp; lepas file di sini</h5>
                                <p class="text-muted mb-3">atau klik untuk memilih file Excel</p>
                                <span class="btn btn-primary">
                                    <i class="fas fa-folder-open mr-1"></i> Pilih File Excel
                                </span>
                                <div class="small text-muted mt-2">Format didukung: .xlsx / .xls (maks 15MB)</div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <small class="text-muted">
                                    <i class="fas fa-robot mr-1 text-primary"></i>
                                    Import dibantu AI: deteksi otomatis merge carton, warna, size, dan revisi.
                                </small>
                                <button class="btn btn-sm btn-outline-primary mt-2 mt-md-0"
                                    data-toggle="modal" data-target="#aiImportModal">
                                    <i class="fas fa-magic mr-1"></i> Mulai Import
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Packing Lists -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-clock mr-1"></i> Packing List Terbaru
                            </h6>
                            <button class="btn btn-sm btn-outline-secondary" id="btnReloadRecent">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-hover" id="tableRecentPackingLists" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="text-center">Tipe</th>
                                            <th>Packing List</th>
                                            <th class="text-center">Revisi</th>
                                            <th class="text-right">Total Carton</th>
                                            <th class="text-center">Valid</th>
                                            <th class="text-center">Broken</th>
                                            <th>File</th>
                                            <th>Tanggal</th>
                                            <th class="text-center" width="120">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="text-right mt-2">
                                <a href="{{ route('jancode.packingLists') }}" class="btn btn-sm btn-link">
                                    Lihat semua packing list <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            @include('layout.footer')

            <!-- AI Import Modal (Wide) -->
            <div class="modal fade" id="aiImportModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
                <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 95vw;" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title font-weight-bold">
                                <i class="fas fa-robot mr-2"></i> AI-Assisted Import Master Packing List
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body p-4">
                            <!-- Step 1: Upload Area -->
                            <div id="aiUploadSection">
                                <div class="alert alert-info border-left-info shadow-sm mb-4">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-info-circle fa-2x mr-3 text-info"></i>
                                        <div>
                                            <strong>Ekstraksi Otomatis AI (Format Garment Baru):</strong><br>
                                            Sistem akan membaca file packing list <code>.xlsx / .xls</code> asli, mendeteksi carton merge, forward-fill warna vertikal, menyaring hanya baris <strong>SOLID tunggal</strong>, dan mengabaikan ASSORT PACK serta carton campuran sesuai aturan PRD.
                                        </div>
                                    </div>
                                </div>
                                <form id="aiUploadForm" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label class="font-weight-bold text-dark">Pilih File Excel Packing List (.xlsx / .xls)</label>
                                        <div class="custom-file">
                                            <input type="file" name="file" class="custom-file-input" id="aiExcelFile" accept=".xlsx,.xls" required>
                                            <label class="custom-file-label" for="aiExcelFile">Pilih file .xlsx / .xls (Maks 15MB)...</label>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <button type="button" class="btn btn-secondary mr-2" data-dismiss="modal">Tutup</button>
                                        <button type="submit" class="btn btn-primary" id="btnProcessAi">
                                            <i class="fas fa-magic mr-1"></i> Mulai Analisis AI
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Processing State -->
                            <div id="aiLoadingSection" class="text-center py-5 d-none">
                                <div class="spinner-border text-primary" style="width: 3.5rem; height: 3.5rem;" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <h5 class="mt-3 font-weight-bold text-gray-800" id="aiLoadingText">Membaca file Excel & menganalisis merge carton...</h5>
                                <p class="text-muted small">Menjalankan ekstraksi dan validasi deterministik (R1 - R6)...</p>
                            </div>

                            <!-- Step 2: Preview Area -->
                            <div id="aiPreviewSection" class="d-none">
                                <!-- Sheet (Packing List) Selector -->
                                <div class="card border-left-dark shadow-sm mb-3" id="aiSheetSelectorCard">
                                    <div class="card-body py-3">
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
                                <div id="cartonMismatchAlert" class="alert alert-warning border-left-warning d-none">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>Peringatan Verifikasi Total Carton:</strong>
                                    Hasil ekstraksi tidak sepenuhnya sinkron dengan hitungan carton deterministik. Harap tinjau dengan seksama sebelum konfirmasi.
                                </div>

                                <!-- Summary Cards -->
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <div class="card border-left-primary shadow-sm h-100 py-2">
                                            <div class="card-body py-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="text-xs font-weight-bold text-primary text-uppercase">Buyer / Country</span>
                                                    <span id="previewEngineMode"></span>
                                                </div>
                                                <div class="h6 mb-0 font-weight-bold text-gray-800" id="previewBuyerCountry">-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card border-left-info shadow-sm h-100 py-2">
                                            <div class="card-body py-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Style / OCF</div>
                                                <div class="h6 mb-0 font-weight-bold text-gray-800" id="previewStyleOcf">-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card border-left-success shadow-sm h-100 py-2">
                                            <div class="card-body py-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Valid Import (Carton)</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <span id="previewTotalItems">0</span> Baris (<span id="previewTotalCartons">0</span> Carton)
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card border-left-secondary shadow-sm h-100 py-2">
                                            <div class="card-body py-2">
                                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Baris Dilewati (Skipped)</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <span id="previewSkippedCount">0</span> Baris
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tabs: Valid vs Skipped -->
                                <ul class="nav nav-tabs font-weight-bold mb-3" id="aiTab" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active text-success" id="valid-tab" data-toggle="tab" href="#validRowsTab" role="tab">
                                            <i class="fas fa-check-circle mr-1"></i> Baris Valid (<span id="badgeValidCount">0</span>)
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link text-secondary" id="skipped-tab" data-toggle="tab" href="#skippedRowsTab" role="tab">
                                            <i class="fas fa-times-circle mr-1"></i> Baris Dilewati (<span id="badgeSkippedCount">0</span>)
                                        </a>
                                    </li>
                                </ul>

                                <div class="tab-content" id="aiTabContent">
                                    <!-- Tab 1: Valid Rows -->
                                    <div class="tab-pane fade show active" id="validRowsTab" role="tabpanel">
                                        <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
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
                                        <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
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
                        <div class="modal-footer bg-light" id="aiFooter">
                            <button type="button" class="btn btn-secondary" id="btnAiReset">
                                <i class="fas fa-undo mr-1"></i> Upload Ulang
                            </button>
                            <button type="button" class="btn btn-success d-none font-weight-bold" id="btnAiConfirmSave">
                                <i class="fas fa-cloud-upload-alt mr-1"></i> Simpan Packing List
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Page level plugins -->
    <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

            // --- DataTables: Packing List Terbaru ---
            const table = $('#tableRecentPackingLists').DataTable({
                processing: true,
                ajax: {
                    url: "{{ route('jancode.packingLists') }}",
                    type: "GET",
                    dataType: "json",
                    dataSrc: "data",
                },
                columns: [
                    {
                        data: 'doc_type', className: 'text-center',
                        render: d => d === 'actual'
                            ? '<span class="badge badge-success">ACTUAL</span>'
                            : '<span class="badge badge-primary">PROFORMA</span>'
                    },
                    {
                        data: null,
                        render: (d) => `<strong>${d.group_label || '-'}</strong>
                            <br><small class="text-muted">Buyer: ${d.supplier_name || '-'}</small>`
                    },
                    {
                        data: null, className: 'text-center',
                        render: d => `<span class="badge badge-dark">Rev.${d.revision_no}</span>
                            <br><small class="text-muted">${d.revision_count} revisi</small>`
                    },
                    { data: 'total_carton', className: 'text-right font-weight-bold text-primary' },
                    { data: 'valid_rows', className: 'text-center text-success font-weight-bold' },
                    {
                        data: 'skipped_rows', className: 'text-center',
                        render: d => d > 0 ? `<span class="text-danger font-weight-bold">${d}</span>` : `<span class="text-muted">0</span>`
                    },
                    { data: 'original_filename', render: d => d ? `<small class="text-muted">${d}</small>` : '-' },
                    { data: 'import_date' },
                    {
                        data: null, orderable: false, className: 'text-center',
                        render: (d) => {
                            const detail = `{{ url('/jancode/import-batches') }}/${d.id}`;
                            return `<a href="${detail}" class="btn btn-info btn-sm btn-circle" title="Detail & Riwayat Revisi"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('scanner.index') }}?packing_list=${d.id}" class="btn btn-success btn-sm btn-circle ml-1" title="Scan packing list ini"><i class="fas fa-barcode"></i></a>`;
                        }
                    },
                ],
                order: [[7, 'desc']],
                pageLength: 5,
                lengthChange: false,
                language: {
                    processing: '<i class="fas fa-spinner fa-spin mr-1"></i> Memuat data packing list...',
                    search: 'Cari:',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ packing list',
                    infoEmpty: 'Belum ada data',
                    zeroRecords: 'Tidak ada packing list yang cocok',
                    emptyTable: '<div class="text-muted py-2"><i class="fas fa-inbox fa-2x d-block mb-2"></i>Belum ada packing list. Silakan import file Excel terlebih dahulu.</div>',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
                },
            });

            $('#btnReloadRecent').click(function () {
                table.ajax.reload(null, false);
            });

            // --- Dropzone ---
            const dz = document.getElementById('importDropzone');

            ['dragenter', 'dragover'].forEach(function (evt) {
                dz.addEventListener(evt, function (e) { e.preventDefault(); dz.classList.add('dragover'); });
            });
            ['dragleave', 'drop'].forEach(function (evt) {
                dz.addEventListener(evt, function (e) { e.preventDefault(); dz.classList.remove('dragover'); });
            });
            dz.addEventListener('drop', function (e) {
                const files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) {
                    const input = document.getElementById('aiExcelFile');
                    input.files = files;
                    $(input).trigger('change');
                    $('#aiImportModal').modal('show');
                }
            });

            // ==========================================
            // --- AI Import Interactive Workflow ---
            // ==========================================
            let aiParsedData = null;          // payload mentah dari server { sheets: [...] }
            let aiSheets = [];                // daftar sheet (packing list) pada file
            let aiActiveSheet = 0;            // index sheet yang sedang ditampilkan
            let aiOriginalFilename = null;

            function aiSheet() {
                return (aiSheets && aiSheets[aiActiveSheet]) ? aiSheets[aiActiveSheet] : null;
            }

            $('#aiExcelFile').on('change', function () {
                const fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').addClass("selected").html(fileName || 'Pilih file .xlsx...');
            });

            $('#btnAiReset').click(function () {
                $('#aiPreviewSection').addClass('d-none');
                $('#aiLoadingSection').addClass('d-none');
                $('#aiUploadSection').removeClass('d-none');
                $('#btnAiConfirmSave').addClass('d-none');
                $('#aiUploadForm').trigger('reset');
                $('#aiExcelFile').next('.custom-file-label').html('Pilih file .xlsx / .xls (Maks 15MB)...');
                aiParsedData = null;
                aiSheets = [];
                aiActiveSheet = 0;
            });

            $('#aiUploadForm').on('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                $('#aiUploadSection').addClass('d-none');
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
                            $('#btnAiReset').trigger('click');
                            return;
                        }

                        aiParsedData = res.data;
                        aiOriginalFilename = res.original_filename || null;
                        aiSheets = (res.data && res.data.sheets) ? res.data.sheets : [];
                        aiActiveSheet = 0;
                        renderAiPreview();
                    },
                    error: function (xhr) {
                        $('#aiLoadingSection').addClass('d-none');
                        $('#aiUploadSection').removeClass('d-none');
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Terjadi kesalahan saat memproses Excel.';
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

                renderSheetTabs();
                renderActiveSheet();

                $('#aiPreviewSection').removeClass('d-none');
                $('#btnAiConfirmSave').removeClass('d-none');
            }

            function renderSheetTabs() {
                let html = '';
                aiSheets.forEach(function (sheet, idx) {
                    const active = idx === aiActiveSheet;
                    const docType = (sheet.doc_type || 'proforma').toLowerCase();
                    const badge = docType === 'actual'
                        ? '<span class="badge badge-warning text-dark ml-1">ACTUAL</span>'
                        : '<span class="badge badge-info ml-1">PROFORMA</span>';
                    const rev = sheet.next_revision ? `<small class="text-muted ml-1">rev ${sheet.next_revision}</small>` : '';
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
                    html: `Apakah Anda yakin ingin menyimpan <strong>${aiSheets.length}</strong> packing list (${totalItems} baris jancode) ke sistem?<br><small class="text-muted">Setiap packing list disimpan sebagai revisi baru; revisi terbaru menjadi ACTIVE.</small>`,
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
                                            successMsg += `<div class="mb-1"><strong>${b.batch_number}</strong> &middot; ${b.group_label} &middot; <span class="badge badge-primary">${(b.doc_type || '').toUpperCase()}</span> rev ${b.revision_no} &middot; ${b.imported} baris`;
                                            if (b.skipped > 0) { successMsg += ` &middot; <span class="text-warning">${b.skipped} broken</span>`; }
                                            successMsg += '</div>';
                                        });
                                        successMsg += '</div>';
                                    }
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil!',
                                        html: successMsg,
                                    });
                                    $('#aiImportModal').modal('hide');
                                    $('#btnAiReset').trigger('click');
                                    table.ajax.reload(null, false);
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
