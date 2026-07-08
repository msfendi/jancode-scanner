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
                            <a href="javascript:void(0)"
                                class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" id="createNewJancode">
                                <i class="fas fa-plus fa-sm text-white-50"></i> Create Jancode
                            </a>
                            <a href="{{ route('jancode.exportTemplate') }}"
                                class="d-none d-sm-inline-block btn btn-sm btn-info shadow-sm">
                                <i class="fas fa-download fa-sm text-white-50"></i> Download Template
                            </a>
                            <button class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm"
                                data-toggle="modal" data-target="#importModal">
                                <i class="fas fa-file-excel fa-sm text-white-50"></i> Import Excel
                            </button>
                            <button class="d-none d-sm-inline-block btn btn-sm btn-warning shadow-sm"
                                data-toggle="modal" data-target="#importNewFormatModal">
                                <i class="fas fa-file-excel fa-sm text-white-50"></i> Import Excel (New)
                            </button>
                        </div>
                    </div>

                    <!-- DataTales Example -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Data Jancode</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm data-table" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Jancode</th>
                                            <th>Size</th>
                                            <th>Qty</th>
                                            <th>Destination</th>
                                            <th>Buyer</th>
                                            <th>Style</th>
                                            <th>Color</th>
                                            <th>CPO</th>
                                            <th>Scanned</th>
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
                    { data: 'jancode', name: 'jancode' },
                    { data: 'size', name: 'size' },
                    { data: 'qty', name: 'qty' },
                    { data: 'destination', name: 'destination' },
                    { data: 'buyer', name: 'buyer' },
                    { data: 'style', name: 'style' },
                    { data: 'color', name: 'color' },
                    { data: 'cpo', name: 'cpo' },
                    { data: 'scanned', name: 'scanned', searchable: false },
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

                    if (data.logs.length === 0) {
                        $('#detail-sessions').html(
                            '<div class="text-center text-muted py-4">' +
                            '<i class="fas fa-inbox fa-2x mb-2"></i>' +
                            '<p>Belum ada scan untuk jancode ini</p>' +
                            '</div>'
                        );
                        return;
                    }

                    let html = '<div class="table-responsive" style="max-height:300px; overflow-y:auto;">';
                    html += '  <table class="table table-sm table-bordered table-striped mb-0">';
                    html += '    <thead class="thead-light"><tr><th width="60">No</th><th>Waktu Scan</th><th>Scanned By</th></tr></thead>';
                    html += '    <tbody>';
                    data.logs.forEach(function (log) {
                        html += '<tr><td>' + log.no + '</td><td>' + log.created_at + '</td><td>' + log.user_name + '</td></tr>';
                    });
                    html += '    </tbody>';
                    html += '  </table>';
                    html += '</div>';

                    $('#detail-sessions').html(html);
                }).fail(function () {
                    $('#detail-sessions').html(
                        '<div class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data scan</div>'
                    );
                });
            });
        });
    </script>

</html>