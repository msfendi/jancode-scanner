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
                        <h1 class="h3 mb-0 text-gray-800">Jancode List</h1>
                        <div>
                            {{-- <a href="javascript:void(0)"
                                class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" id="createNewJancode">
                                <i class="fas fa-plus fa-sm text-white-50"></i> Create Jancode
                            </a>
                            <a href="{{ route('jancode.exportTemplate') }}"
                                class="d-none d-sm-inline-block btn btn-sm btn-info shadow-sm">
                                <i class="fas fa-download fa-sm text-white-50"></i> Download Template
                            </a> --}}
                            <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"
                                data-toggle="modal" data-target="#aiImportModal">
                                <i class="fas fa-robot fa-sm text-white-50"></i> AI Import Packing List
                            </button>
                            <a href="{{ route('jancode.importBatches') }}"
                                class="d-none d-sm-inline-block btn btn-sm btn-outline-secondary shadow-sm ml-2">
                                <i class="fas fa-history fa-sm mr-1"></i> Riwayat Import
                            </a>
                            {{-- <button class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm"
                                data-toggle="modal" data-target="#importModal">
                                <i class="fas fa-file-excel fa-sm text-white-50"></i> Import Excel
                            </button>
                            <button class="d-none d-sm-inline-block btn btn-sm btn-warning shadow-sm"
                                data-toggle="modal" data-target="#importNewFormatModal">
                                <i class="fas fa-file-excel fa-sm text-white-50"></i> Import Excel (New)
                            </button> --}}
                        </div>
                    </div>

                    <!-- DataTales Example -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Data Master Jancode (Struktur Baru)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm data-table" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Country</th>
                                            <th>Buyer</th>
                                            <th>OCF</th>
                                            <th>Style</th>
                                            <th>Color</th>
                                            <th>Size</th>
                                            <th>JAN Code</th>
                                            <th>Packing List (Carton)</th>
                                            <th>Pcs/Ctn</th>
                                            <th>Total Pcs</th>
                                            <th>Received</th>
                                            <th>Balance</th>
                                            <th>Void</th>
                                            <th width="140px">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
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

            <!-- Jancode Modal -->

            <div class="modal fade" id="ajaxModel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" id="modelHeading"></h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form id="jancodeForm" name="jancodeForm" class="form-horizontal">
                                <input type="hidden" name="id" id="jancode_id">
                                <div class="form-group">
                                    <label for="jancode" class="col-sm-12 control-label">Jancode</label>
                                    <div class="col-sm-12">
                                        <input type="text" class="form-control" id="jancode" name="jancode"
                                            placeholder="Enter Jancode" value="" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="size" class="col-sm-12 control-label">Size</label>
                                    <div class="col-sm-12">
                                        <input type="text" class="form-control" id="size" name="size"
                                            placeholder="Enter Size" value="" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-12 control-label">Qty</label>
                                    <div class="col-sm-12">
                                        <input type="number" id="qty" name="qty" required placeholder="Enter Qty"
                                            class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-12 control-label">Destination</label>
                                    <div class="col-sm-12">
                                        <input type="text" id="destination" name="destination" required
                                            placeholder="Enter Destination" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-12 control-label">Buyer</label>
                                    <div class="col-sm-12">
                                        <input type="text" id="buyer" name="buyer" required placeholder="Enter Buyer"
                                            class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-12 control-label">Style</label>
                                    <div class="col-sm-12">
                                        <input type="text" id="style" name="style" required placeholder="Enter Style"
                                            class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-12 control-label">CPO</label>
                                    <div class="col-sm-12">
                                        <input type="text" id="cpo" name="cpo" required placeholder="Enter CPO"
                                            class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-12 control-label">Void</label>
                                    <div class="col-sm-12">
                                        <select name="void" id="void" class="form-control">
                                            <option value="0">False</option>
                                            <option value="1">True</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-offset-2 col-sm-10 mt-3">
                                    <button type="submit" class="btn btn-primary" id="saveBtn" value="create">Save
                                        changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Import Modal -->
            <div class="modal fade" id="aiImportModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
                <div class="modal-dialog modal-xl" role="document">
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
                                            Sistem akan membaca file packing list <code>.xlsx</code> asli, mendeteksi carton merge, forward-fill warna vertikal, menyaring hanya baris <strong>SOLID tunggal</strong>, dan mengabaikan ASSORT PACK serta carton campuran sesuai aturan PRD.
                                        </div>
                                    </div>
                                </div>
                                <form id="aiUploadForm" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label class="font-weight-bold text-dark">Pilih File Excel Packing List (.xlsx)</label>
                                        <div class="custom-file">
                                            <input type="file" name="file" class="custom-file-input" id="aiExcelFile" accept=".xlsx" required>
                                            <label class="custom-file-label" for="aiExcelFile">Pilih file .xlsx (Maks 5MB)...</label>
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
                                <p class="text-muted small">Menjalankan ekstraksi AI dan validasi deterministik (R1 - R6)...</p>
                            </div>

                            <!-- Step 2: Preview Area -->
                            <div id="aiPreviewSection" class="d-none">
                                <!-- Warning banner if mismatch -->
                                <div id="cartonMismatchAlert" class="alert alert-warning border-left-warning d-none">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>Peringatan Verifikasi Total Carton:</strong>
                                    Hasil ekstraksi AI tidak sepenuhnya sinkron dengan hitungan carton deterministik. Harap tinjau dengan seksama sebelum konfirmasi.
                                </div>

                                <!-- Summary Cards -->
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <div class="card border-left-primary shadow-sm h-100 py-2">
                                            <div class="card-body py-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Buyer / Country</div>
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
                                                        <th width="50">Row</th>
                                                        <th>Color</th>
                                                        <th>JAN Code</th>
                                                        <th>Size</th>
                                                        <th width="90" class="text-right">Karton</th>
                                                        <th width="80" class="text-right">Pcs/Ctn</th>
                                                        <th width="90" class="text-right">Total Pcs</th>
                                                        <th width="85" class="text-center">Status</th>
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
                                <i class="fas fa-cloud-upload-alt mr-1"></i> Simpan ke Master Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Modal -->
            <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form id="importForm" action="{{ route('jancode.import') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Import Excel</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>Upload Excel File</label>
                                    <input type="file" name="file" class="form-control" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Import</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Import New Format Modal -->
            <div class="modal fade" id="importNewFormatModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form id="importNewFormatForm" action="{{ route('jancode.importNewFormat') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Import Excel (New Format)</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>Upload Excel File</label>
                                    <input type="file" name="file" class="form-control" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-warning">Import (New)</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Detail Scan Modal -->
            <div class="modal fade" id="detailScanModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title"><i class="fas fa-search mr-1"></i> Detail Scan - <span
                                    id="detail-jancode"></span></h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <!-- Product Info -->
                            {{-- <div class="col-md-12">
                                <div class="row mb-12">
                                    <div class="col-md-4">
                                        <small class="text-muted">Description</small>
                                        <p class="font-weight-bold mb-0" id="detail-desc">-</p>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Size</small>
                                        <p class="font-weight-bold mb-0" id="detail-size">-</p>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Color</small>
                                        <p class="font-weight-bold mb-0" id="detail-color">-</p>
                                    </div>
                                </div>
                                <div class="row mb-12">
                                    <div class="col-md-2">
                                        <small class="text-muted">Qty Master</small>
                                        <p class="font-weight-bold mb-0" id="detail-qty">-</p>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted">Balance</small>
                                        <p class="font-weight-bold mb-0" id="detail-balance">-</p>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted">Total Scanned</small>
                                        <p class="font-weight-bold mb-0" id="detail-total">-</p>
                                    </div>
                                </div>
                            </div> --}}
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div>
                                        <small class="text-muted">Destination / Buyer</small>
                                        <p class="font-weight-bold mb-0" id="detail-desc">-</p>
                                    </div>
                                    <div>
                                        <small class="text-muted">Qty Master</small>
                                        <h3 class="font-weight-bold mb-0 text-primary" id="detail-qty">-</h3>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div>
                                        <small class="text-muted">Size</small>
                                        <p class="font-weight-bold mb-4" id="detail-size">-</p>
                                    </div>
                                    <div>
                                        <small class="text-muted">Total Scanned</small>
                                        <h3 class="font-weight-bold mb-0 text-success" id="detail-total">-</h3>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div>
                                        <small class="text-muted">Style / CPO</small>
                                        <p class="font-weight-bold mb-4" id="detail-color">-</p>
                                    </div>
                                    <div>
                                        <small class="text-muted">Balance</small>
                                        <h3 class="font-weight-bold mb-0 text-danger" id="detail-balance">-</h3>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <!-- Session List -->
                            <div id="detail-sessions">
                                <p class="text-center text-muted py-3">Loading...</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <!-- End of Page Wrapper -->

    <!-- Page level plugins -->
    <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script type="text/javascript">
        $(function () {
            // --- Ajax Setup ---
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // --- DataTables Initialization ---
            const table = $('.data-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('jancode.index') }}",
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'country', name: 'country' },
                    { data: 'buyer', name: 'buyer' },
                    { data: 'ocf', name: 'ocf' },
                    { data: 'style', name: 'style' },
                    { data: 'color', name: 'color' },
                    { data: 'size', name: 'size' },
                    { data: 'jancode', name: 'jancode' },
                    { data: 'packing_qty', name: 'packing_list_qty_carton' },
                    { data: 'qty_per_carton', name: 'qty_per_carton' },
                    { data: 'total_qty_pcs', name: 'total_qty_pcs' },
                    { data: 'received_qty', name: 'receive_qty_carton', searchable: false },
                    { data: 'balance', name: 'balance', searchable: false },
                    { data: 'void', name: 'void', render: function (data) { return data == 1 ? '<span class="badge badge-danger">True</span>' : '<span class="badge badge-success">False</span>'; } },
                    { data: 'action', name: 'action', orderable: false, searchable: false },
                ]
            });

            // --- Show Create Modal ---
            $('#createNewJancode').click(function () {
                $('#saveBtn').val("create-jancode").html('Save changes');
                $('#jancode_id').val('');
                $('#jancodeForm').trigger("reset");
                $('#modelHeading').html("Create New Jancode");
                $('#ajaxModel').modal('show');
            });

            // --- Show Edit Modal ---
            $('body').on('click', '.editJancode', function () {
                const jancode_id = $(this).data('id');
                const editUrl = "{{ route('jancode.edit', ':id') }}".replace(':id', jancode_id);
                $.get(editUrl, function (data) {
                    $('#modelHeading').html("Edit Jancode");
                    $('#saveBtn').val("edit-jancode").html('Save changes');
                    $('#ajaxModel').modal('show');
                    $('#jancode_id').val(data.id);
                    $('#jancode').val(data.jancode);
                    $('#size').val(data.size);
                    $('#qty').val(data.qty);
                    $('#destination').val(data.destination);
                    $('#buyer').val(data.buyer);
                    $('#style').val(data.style);
                    $('#cpo').val(data.cpo);
                    $('#void').val(data.void);
                });
            });

            // --- Create/Edit Form Submit ---
            $('#jancodeForm').submit(function (e) {
                e.preventDefault();
                const formData = new FormData(this);

                Swal.fire({
                    title: 'Saving Data...',
                    text: 'Please wait while we save your changes.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    data: formData,
                    url: "{{ route('jancode.store') }}",
                    type: "POST",
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        $('#jancodeForm').trigger("reset");
                        $('#ajaxModel').modal('hide');
                        table.draw();
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.success,
                            confirmButtonText: 'OK'
                        });
                    },
                    error: function (xhr) {
                        const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred while saving.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: errorMsg,
                            confirmButtonText: 'Try Again'
                        });
                    }
                });
            });

            // --- Delete Action ---
            $('body').on('click', '.deleteJancode', function () {
                const jancode_id = $(this).data("id");

                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Deleting Data...',
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading()
                        });

                        const formData = new FormData();
                        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                        formData.append('_method', 'DELETE');

                        $.ajax({
                            type: "POST",
                            url: "{{ route('jancode.index') }}" + '/' + jancode_id,
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function (response) {
                                table.draw();
                                Swal.fire('Deleted!', response.success, 'success');
                            },
                            error: function (xhr) {
                                Swal.fire('Error!', 'Failed to delete data.', 'error');
                            }
                        });
                    }
                });
            });

            // --- Import Form Submit ---
            $('#importForm').on('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(this);

                Swal.fire({
                    title: 'Importing Data...',
                    text: 'Please wait while we process the Excel file.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            table.draw();
                            $('#importForm')[0].reset();
                            $('#importModal').modal('hide');
                        });
                    },
                    error: function (xhr) {
                        const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred during import.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Import Failed',
                            text: errorMsg,
                            confirmButtonText: 'Try Again'
                        });
                    }
                });
            });

            // --- Import New Format Form Submit ---
            $('#importNewFormatForm').on('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(this);

                Swal.fire({
                    title: 'Importing Data...',
                    text: 'Please wait while we process the Excel file.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            table.draw();
                            $('#importNewFormatForm')[0].reset();
                            $('#importNewFormatModal').modal('hide');
                        });
                    },
                    error: function (xhr) {
                        const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred during import.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Import Failed',
                            text: errorMsg,
                            confirmButtonText: 'Try Again'
                        });
                    }
                });
            });

            // --- Detail Scan Action ---
            $('body').on('click', '.detailScan', function () {
                const jancode_id = $(this).data('id');
                const jancode = $(this).data('jancode');

                $('#detail-jancode').text(jancode);
                $('#detail-sessions').html('<p class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Loading...</p>');
                $('#detailScanModal').modal('show');

                const scanLogUrl = "{{ route('jancode.scanLogs', ':id') }}".replace(':id', jancode_id);

                $.get(scanLogUrl, function (data) {
                    $('#detail-desc').text((data.destination || '-') + ' / ' + (data.buyer || '-'));
                    $('#detail-size').text(data.size || '-');
                    $('#detail-color').text((data.style || '-') + ' / ' + (data.cpo || '-'));
                    $('#detail-qty').text(data.qty);
                    $('#detail-balance').text(data.balance);
                    $('#detail-total').text(data.total_scans);

                    if (data.logs.length === 0 && (!data.batches || data.batches.length === 0)) {
                        $('#detail-sessions').html(
                            '<div class="text-center text-muted py-4">' +
                            '<i class="fas fa-inbox fa-2x mb-2"></i>' +
                            '<p>Belum ada aktivitas scan untuk jancode ini</p>' +
                            '</div>'
                        );
                        return;
                    }

                    let html = '';

                    // 1. Tabel Riwayat Batch Submit
                    if (data.batches && data.batches.length > 0) {
                        html += '<h6 class="font-weight-bold text-primary mb-2"><i class="fas fa-layer-group mr-1"></i> Riwayat Batch Scan Submit:</h6>';
                        html += '<div class="table-responsive mb-3" style="max-height:200px; overflow-y:auto;">';
                        html += '  <table class="table table-sm table-bordered table-hover mb-0">';
                        html += '    <thead class="thead-light"><tr><th>Batch No</th><th>Waktu Submit</th><th class="text-right">Qty Di-Scan</th><th class="text-right">Akumulasi Receive</th><th class="text-right">Sisa Balance</th><th>Operator</th></tr></thead>';
                        html += '    <tbody>';
                        data.batches.forEach(function (b) {
                            html += `<tr>
                                <td><span class="badge badge-dark font-mono">${b.batch_no}</span></td>
                                <td><small class="text-muted">${b.created_at}</small></td>
                                <td class="text-right font-weight-bold text-success">+${b.scanned_qty} ctn</td>
                                <td class="text-right font-weight-bold text-primary">${b.receive_qty} ctn</td>
                                <td class="text-right font-weight-bold ${b.balance > 0 ? 'text-warning' : 'text-success'}">${b.balance} ctn</td>
                                <td><small>${b.user_name}</small></td>
                            </tr>`;
                        });
                        html += '    </tbody>';
                        html += '  </table>';
                        html += '</div>';
                    }

                    // 2. Tabel Log Scan Individual
                    if (data.logs && data.logs.length > 0) {
                        html += '<h6 class="font-weight-bold text-secondary mb-2"><i class="fas fa-barcode mr-1"></i> Log Scan Fisik (Per Karton):</h6>';
                        html += '<div class="table-responsive" style="max-height:220px; overflow-y:auto;">';
                        html += '  <table class="table table-sm table-bordered table-striped mb-0">';
                        html += '    <thead class="thead-light"><tr><th width="60">No</th><th>Waktu Scan</th><th>Scanned By</th></tr></thead>';
                        html += '    <tbody>';
                        data.logs.forEach(function (log) {
                            html += '<tr><td>' + log.no + '</td><td>' + log.created_at + '</td><td>' + log.user_name + '</td></tr>';
                        });
                        html += '    </tbody>';
                        html += '  </table>';
                        html += '</div>';
                    }

                    $('#detail-sessions').html(html);
                }).fail(function () {
                    $('#detail-sessions').html(
                        '<div class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data scan</div>'
                    );
                });
            });
            // ==========================================
            // --- AI Import Interactive Workflow ---
            // ==========================================
            let aiParsedData = null;
            let aiOriginalFilename = null;

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
                $('#aiExcelFile').next('.custom-file-label').html('Pilih file .xlsx (Maks 5MB)...');
                aiParsedData = null;
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
                        renderAiPreview(aiParsedData);
                    },
                    error: function (xhr) {
                        $('#aiLoadingSection').addClass('d-none');
                        $('#aiUploadSection').removeClass('d-none');
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Terjadi kesalahan saat memproses Excel.';
                        Swal.fire('Proses Gagal', msg, 'error');
                    }
                });
            });

            function renderAiPreview(data) {
                $('#previewBuyerCountry').text((data.header.buyer || '-') + ' / ' + (data.header.country || '-'));
                $('#previewStyleOcf').text((data.header.style || '-') + ' / ' + (data.header.ocf || '-'));
                updateSummaryCounters();

                if (!data.carton_match) {
                    $('#cartonMismatchAlert').removeClass('d-none');
                } else {
                    $('#cartonMismatchAlert').addClass('d-none');
                }

                // Render Valid Rows
                renderValidRows();

                // Render Skipped Rows
                renderSkippedRows(data.skipped || []);

                $('#aiPreviewSection').removeClass('d-none');
                $('#btnAiConfirmSave').removeClass('d-none');
            }

            function updateSummaryCounters() {
                const totalItems = aiParsedData.items.length;
                let totalCarton = 0;
                aiParsedData.items.forEach(it => {
                    totalCarton += parseInt(it.packing_list_qty_carton) || 0;
                });

                $('#previewTotalItems').text(totalItems);
                $('#previewTotalCartons').text(totalCarton);
                $('#previewSkippedCount').text(aiParsedData.skipped.length);
                $('#badgeValidCount').text(totalItems);
                $('#badgeSkippedCount').text(aiParsedData.skipped.length);

                if (totalItems === 0) {
                    $('#btnAiConfirmSave').prop('disabled', true);
                } else {
                    $('#btnAiConfirmSave').prop('disabled', false);
                }
            }

            function renderValidRows() {
                let html = '';
                if (!aiParsedData.items || aiParsedData.items.length === 0) {
                    html = '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada baris valid yang ditemukan.</td></tr>';
                } else {
                    aiParsedData.items.forEach(function (item, idx) {
                        const statusBadge = item.is_duplicate 
                            ? '<span class="badge badge-warning text-dark"><i class="fas fa-sync-alt mr-1"></i> Update</span>' 
                            : '<span class="badge badge-success"><i class="fas fa-plus mr-1"></i> Baru</span>';

                        const pcsPerCarton = parseInt(item.qty_per_carton) || 0;
                        const packingCarton = parseInt(item.packing_list_qty_carton) || 0;
                        const totalPcs = item.total_qty_pcs || (packingCarton * pcsPerCarton);

                        html += `<tr data-index="${idx}">
                            <td class="align-middle text-muted">${item.source_row}</td>
                            <td class="align-middle font-weight-bold">${item.color}</td>
                            <td class="align-middle font-mono">${item.jan_code}</td>
                            <td class="align-middle"><span class="badge badge-primary px-2">${item.size}</span></td>
                            <td class="align-middle text-right font-weight-bold text-primary">${packingCarton}</td>
                            <td class="align-middle text-right font-weight-bold text-dark">${pcsPerCarton}</td>
                            <td class="align-middle text-right font-weight-bold text-success">${totalPcs.toLocaleString()}</td>
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

            // Hapus baris preview
            $(document).on('click', '.btn-remove-preview-row', function () {
                const idx = $(this).data('index');
                if (aiParsedData && aiParsedData.items[idx]) {
                    aiParsedData.items.splice(idx, 1);
                    renderValidRows();
                    updateSummaryCounters();
                }
            });

            // Konfirmasi & Simpan ke Master Data
            $('#btnAiConfirmSave').click(function () {
                if (!aiParsedData || aiParsedData.items.length === 0) {
                    Swal.fire('Perhatian', 'Tidak ada baris data untuk diimport.', 'warning');
                    return;
                }

                Swal.fire({
                    title: 'Konfirmasi Import Master',
                    text: `Apakah Anda yakin ingin menyimpan ${aiParsedData.items.length} data master JAN Code ke sistem?`,
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
                                items: aiParsedData.items,
                                skipped: aiParsedData.skipped || [],
                                original_filename: aiOriginalFilename,
                            }),
                            success: function (res) {
                                btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt mr-1"></i> Simpan ke Master Data');
                                if (res.success) {
                                    let successMsg = res.message;
                                    if (res.batch_number) {
                                        successMsg += `\n\nBatch: <strong>${res.batch_number}</strong>`;
                                        if (res.failed_count > 0) {
                                            successMsg += `<br>${res.failed_count} baris gagal tersimpan di riwayat import.`;
                                        }
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
                                btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt mr-1"></i> Simpan ke Master Data');
                                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Terjadi kegagalan saat menyimpan data.';
                                Swal.fire('Gagal!', msg, 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>

</html>