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
                        <h1 class="h3 mb-0 text-gray-800">Riwayat Import Packing List</h1>
                        <a href="{{ route('jancode.index') }}" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm mr-1"></i> Kembali ke Master Jancode
                        </a>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Daftar Batch Import</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="tableBatches" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Batch No</th>
                                            <th>Nama Batch</th>
                                            <th>File</th>
                                            <th>OCF / Supplier</th>
                                            <th>Tanggal</th>
                                            <th>Status</th>
                                            <th class="text-center">Total Row</th>
                                            <th class="text-center">Valid</th>
                                            <th class="text-center">Gagal</th>
                                            <th class="text-center">Processed</th>
                                            <th>Imported By</th>
                                            <th>Catatan</th>
                                            <th width="80">Detail</th>
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
            const statusBadge = {
                'completed': '<span class="badge badge-success">Completed</span>',
                'partial':   '<span class="badge badge-warning text-dark">Partial</span>',
                'failed':    '<span class="badge badge-danger">Failed</span>',
                'processing':'<span class="badge badge-info">Processing</span>',
                'pending':   '<span class="badge badge-secondary">Pending</span>',
            };

            const table = $('#tableBatches').DataTable({
                processing: true,
                ajax: {
                    url: "{{ route('jancode.importBatches') }}",
                    type: "GET",
                    dataType: "json",
                    dataSrc: "data",
                },
                columns: [
                    { data: null, render: (d, t, r, m) => m.row + 1, orderable: false },
                    { data: 'batch_number', render: d => `<code>${d}</code>` },
                    { data: 'batch_name' },
                    { data: 'original_filename', render: d => d ? `<small class="text-muted">${d}</small>` : '-' },
                    { data: 'ocf_number', render: (d, t, r) => `<strong>${d || '-'}</strong><br><small>${r.supplier_name || ''}</small>` },
                    { data: 'import_date' },
                    { data: 'status', render: d => statusBadge[d] || d },
                    { data: 'total_rows', className: 'text-center' },
                    { data: 'valid_rows', className: 'text-center', render: d => `<span class="text-success font-weight-bold">${d}</span>` },
                    { data: 'invalid_rows', className: 'text-center', render: d => d > 0 ? `<span class="text-danger font-weight-bold">${d}</span>` : `<span class="text-muted">${d}</span>` },
                    { data: 'processed_items', className: 'text-center', render: d => `<span class="text-primary font-weight-bold">${d}</span>` },
                    { data: 'imported_by' },
                    { data: 'import_notes', render: d => d ? `<small class="text-muted">${d}</small>` : '-' },
                    { data: 'id', orderable: false, render: d => `<a href="{{ url('/jancode/import-batches') }}/${d}" class="btn btn-info btn-sm btn-circle"><i class="fas fa-eye"></i></a>` },
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json' },
            });
        });
    </script>
</body>
</html>
