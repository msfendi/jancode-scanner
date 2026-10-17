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
                                <i class="fas fa-tasks text-primary mr-1"></i> Manage Packing List
                            </h1>
                            <p class="mb-0 text-muted small">
                                Langkah <strong>2</strong>: satu baris = satu packing list (revisi <strong>aktif</strong>).
                                Klik <i class="fas fa-eye"></i> untuk melihat riwayat revisi &amp; baris broken.
                            </p>
                        </div>
                        <div>
                            <a href="{{ route('jancode.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                                <i class="fas fa-file-import fa-sm mr-1"></i> Import Packing List
                            </a>
                            <a href="{{ route('scanner.index') }}" class="btn btn-sm btn-success shadow-sm ml-1">
                                <i class="fas fa-barcode fa-sm mr-1"></i> Scan Jancode
                            </a>
                        </div>
                    </div>

                    @include('layout.workflow-steps', ['current' => 'manage'])

                    <!-- Penjelasan -->
                    <div class="alert alert-light border shadow-sm mb-4 small">
                        <div class="d-flex">
                            <i class="fas fa-lightbulb fa-lg text-warning mr-3"></i>
                            <div>
                                Daftar di bawah hanya menampilkan <strong>revisi aktif</strong> tiap packing list.
                                Grouping: <strong>Style &rarr; CPO/OCF &rarr; Destination &rarr; Warehouse</strong>.
                                Jika sudah ada dokumen <span class="badge badge-success">ACTUAL</span>, revisi itulah yang
                                menjadi acuan scan; bila belum ada, revisi <span class="badge badge-primary">PROFORMA</span>
                                terbaru yang dipakai.
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-clipboard-list mr-1"></i> Daftar Packing List Aktif
                            </h6>
                            <a href="{{ route('jancode.index') }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus mr-1"></i> Import Baru
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-hover" id="tablePackingLists" width="100%">
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
                                            <th class="text-center" width="120">Aksi</th>
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
                language: {
                    processing: '<i class="fas fa-spinner fa-spin mr-1"></i> Memuat data packing list...',
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ packing list',
                    infoEmpty: 'Belum ada data',
                    zeroRecords: 'Tidak ada packing list yang cocok',
                    emptyTable: '<div class="text-muted py-3"><i class="fas fa-inbox fa-2x d-block mb-2"></i>Belum ada packing list. Silakan <a href="{{ route('jancode.index') }}">import packing list</a> terlebih dahulu.</div>',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
                },
            });
        });
    </script>
</body>
</html>
