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
                            <h1 class="h3 mb-0 text-gray-800">Riwayat Import Packing List</h1>
                            <p class="mb-0 text-muted small">Seluruh riwayat upload (revisi PROFORMA maupun ACTUAL).</p>
                        </div>
                        <div>
                            <a href="{{ route('jancode.packingLists') }}" class="btn btn-sm btn-outline-primary shadow-sm mr-1">
                                <i class="fas fa-clipboard-list fa-sm mr-1"></i> Packing List Aktif
                            </a>
                            <a href="{{ route('jancode.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                                <i class="fas fa-arrow-left fa-sm mr-1"></i> Master Jancode
                            </a>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Daftar Batch Import (Revisi)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="tableBatches" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Batch No</th>
                                            <th>Packing List (Group)</th>
                                            <th class="text-center">Tipe</th>
                                            <th class="text-center">Revisi</th>
                                            <th class="text-center">Status Aktif</th>
                                            <th>File / Sheet</th>
                                            <th>Tanggal</th>
                                            <th class="text-center">Total Carton</th>
                                            <th class="text-center">Valid</th>
                                            <th class="text-center">Broken</th>
                                            <th>Imported By</th>
                                            <th width="70">Detail</th>
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

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

    <script>
        $(document).ready(function () {
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
                    { data: 'group_label', render: (d, t, r) => `<strong>${d || '-'}</strong><br><small class="text-muted">${r.ocf_number || '-'} &middot; ${r.destination || '-'}${r.warehouse ? ' &middot; ' + r.warehouse : ''}</small>` },
                    {
                        data: 'doc_type', className: 'text-center',
                        render: d => d === 'actual'
                            ? '<span class="badge badge-success">ACTUAL</span>'
                            : '<span class="badge badge-primary">PROFORMA</span>'
                    },
                    { data: 'revision_no', className: 'text-center', render: d => `<span class="badge badge-dark">Rev.${d}</span>` },
                    {
                        data: 'is_active', className: 'text-center',
                        render: d => d
                            ? '<span class="badge badge-success"><i class="fas fa-check mr-1"></i>Aktif</span>'
                            : '<span class="badge badge-secondary">Superseded</span>'
                    },
                    { data: 'original_filename', render: (d, t, r) => d ? `<small class="text-muted">${d}</small>` + (r.sheet_name ? `<br><span class="badge badge-light border">${r.sheet_name}</span>` : '') : '-' },
                    { data: 'created_at' },
                    { data: 'total_carton', className: 'text-right font-weight-bold text-primary' },
                    { data: 'valid_rows', className: 'text-center', render: d => `<span class="text-success font-weight-bold">${d}</span>` },
                    { data: 'invalid_rows', className: 'text-center', render: d => d > 0 ? `<span class="text-danger font-weight-bold">${d}</span>` : `<span class="text-muted">${d}</span>` },
                    { data: 'imported_by' },
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
