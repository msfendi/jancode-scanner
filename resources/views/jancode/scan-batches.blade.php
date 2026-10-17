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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="fas fa-history text-primary mr-1"></i> Riwayat Batch Scan Jancode
                            </h1>
                            <p class="mb-0 text-muted small">
                                Daftar sesi <strong>submit</strong> hasil scanning karton per packing list.
                            </p>
                        </div>
                        <div>
                            <a href="{{ route('scanner.index') }}" class="btn btn-sm btn-primary shadow-sm mr-2">
                                <i class="fas fa-barcode fa-sm mr-1"></i> Buka Scanner
                            </a>
                            <a href="{{ route('jancode.packingLists') }}" class="btn btn-sm btn-outline-primary shadow-sm">
                                <i class="fas fa-tasks fa-sm mr-1"></i> Manage Packing List
                            </a>
                        </div>
                    </div>

                    @include('layout.workflow-steps', ['current' => 'scan'])

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-history mr-1"></i> Daftar Sesi Submit Batch Scan
                            </h6>
                            <button class="btn btn-sm btn-outline-primary" id="btnRefresh">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-hover" id="tableScanBatches" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="40">#</th>
                                            <th>Waktu Submit</th>
                                            <th>Batch No</th>
                                            <th>Packing List</th>
                                            <th>JAN Code</th>
                                            <th>Color</th>
                                            <th>Size</th>
                                            <th>Style / Country</th>
                                            <th class="text-right">Qty Di-Scan</th>
                                            <th class="text-right">Akumulasi Receive</th>
                                            <th class="text-right">Sisa Balance</th>
                                            <th class="text-right">Total Master</th>
                                            <th>Operator</th>
                                            <th>Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('layout.footer')

    <!-- Page level plugins -->
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

    <script>
        $(document).ready(function () {
            const table = $('#tableScanBatches').DataTable({
                processing: true,
                ajax: {
                    url: "{{ route('scanner.batches') }}",
                    type: "GET",
                    dataType: "json",
                    dataSrc: "data",
                },
                columns: [
                    { data: null, render: (d, t, r, m) => m.row + 1, orderable: false },
                    { data: 'created_at', render: d => `<small class="text-muted">${d}</small>` },
                    { data: 'batch_no', render: d => `<span class="badge badge-dark font-mono px-2 py-1">${d}</span>` },
                    { data: 'packing_list', render: d => `<span class="small font-weight-bold text-primary">${d || '-'}</span>` },
                    { data: 'jancode', render: d => d && d !== '-' ? `<code>${d}</code>` : '<span class="text-muted">—</span>' },
                    { data: 'color', render: d => `<strong>${d}</strong>` },
                    { data: 'size', render: d => `<span class="badge badge-primary px-2">${d}</span>` },
                    { data: 'style', render: (d, t, r) => `<strong>${d}</strong><br><small class="text-muted">${r.country || '-'}</small>` },
                    { data: 'scanned_qty', className: 'text-right font-weight-bold text-success', render: d => `+${d} ctn` },
                    { data: 'receive_qty', className: 'text-right font-weight-bold text-primary', render: d => `${d} ctn` },
                    { data: 'balance', className: 'text-right font-weight-bold', render: d => d > 0 ? `<span class="text-danger">${d} ctn</span>` : `<span class="text-success">0 (Lengkap)</span>` },
                    { data: 'total_qty', className: 'text-right text-muted', render: d => `${d} ctn` },
                    { data: 'user_name', render: d => `<span class="badge badge-light border">${d}</span>` },
                    { data: 'notes', render: d => `<small class="text-muted font-italic">${d || '-'}</small>` },
                ],
                order: [[1, 'desc']],
                pageLength: 25,
            });

            $('#btnRefresh').click(function () {
                table.ajax.reload(null, false);
            });
        });
    </script>
</body>
</html>
