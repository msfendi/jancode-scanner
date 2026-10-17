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
                            <h1 class="h3 mb-0 text-gray-800">Packing List (PROFORMA / ACTUAL)</h1>
                            <p class="mb-0 text-muted small">
                                Satu baris = satu packing list (revisi <strong>aktif</strong>).
                                Grouping: Style → CPO/OCF → Destination → Warehouse.
                            </p>
                        </div>
                        <div>
                            <a href="{{ route('jancode.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                                <i class="fas fa-arrow-left fa-sm mr-1"></i> Master Jancode
                            </a>
                            <a href="{{ route('scanner.index') }}" class="btn btn-sm btn-success shadow-sm ml-1">
                                <i class="fas fa-barcode fa-sm mr-1"></i> Scan Packing List
                            </a>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-clipboard-list mr-1"></i> Daftar Packing List Aktif
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="tablePackingLists" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Style / CPO / Destination / WH</th>
                                            <th class="text-center">Tipe</th>
                                            <th class="text-center">Revisi</th>
                                            <th class="text-center">Total Carton</th>
                                            <th class="text-center">Baris Valid</th>
                                            <th class="text-center">Baris Broken</th>
                                            <th>Batch Aktif</th>
                                            <th>File</th>
                                            <th>Tanggal</th>
                                            <th width="160">Aksi</th>
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
            $('#tablePackingLists').DataTable({
                processing: true,
                ajax: {
                    url: "{{ route('jancode.packingLists') }}",
                    type: "GET",
                    dataType: "json",
                    dataSrc: "data",
                },
                columns: [
                    { data: null, render: (d, t, r, m) => m.row + 1, orderable: false },
                    {
                        data: null,
                        render: (d) => `<strong>${d.group_label || '-'}</strong>
                                <br><small class="text-muted">Buyer: ${d.supplier_name || '-'}</small>`
                    },
                    {
                        data: 'doc_type',
                        className: 'text-center',
                        render: d => d === 'actual'
                            ? '<span class="badge badge-success">ACTUAL</span>'
                            : '<span class="badge badge-primary">PROFORMA</span>'
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
                    { data: 'batch_number', render: d => `<code>${d}</code>` },
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
                order: [[0, 'desc']],
                pageLength: 25,
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json' },
            });
        });
    </script>
</body>
</html>
