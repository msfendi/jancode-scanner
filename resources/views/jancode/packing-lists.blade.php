<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.3.0/dist/select2-bootstrap4.min.css" rel="stylesheet" />

<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')
                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h1 class="h4 mb-0 text-gray-800">
                                <i class="fas fa-tasks text-primary mr-1"></i> Manage Packing List
                            </h1>
                            <p class="mb-0 text-muted small">
                                Langkah <strong>3</strong>: satu baris = satu packing list (revisi <strong>aktif</strong>).
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

                    @include('layout.session-banner', ['currentSession' => $currentSession])

                    @php $hasSession = (bool) $currentSession; @endphp

                    @if (!$hasSession)
                        <div class="card shadow mb-4 border-left-warning">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-lock fa-2x text-warning mb-3"></i>
                                <h5 class="font-weight-bold text-gray-800">Pilih session dulu</h5>
                                <p class="text-muted mb-3">
                                    Data packing list dikelompokkan per session. Mulai atau pilih session untuk melihat datanya.
                                </p>
                                <a href="{{ route('session.index') }}" class="btn btn-primary font-weight-bold">
                                    <i class="fas fa-play-circle mr-1"></i> Ke Halaman Session
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- Penjelasan -->
                    <div class="alert alert-light border shadow-sm mb-3 small py-2">
                        <div class="d-flex">
                            <i class="fas fa-lightbulb fa-lg text-warning mr-3"></i>
                            <div>
                                Daftar di bawah hanya menampilkan <strong>revisi aktif</strong> tiap packing list.
                                Grouping: <strong>Style &rarr; OCF &rarr; Destination &rarr; Warehouse</strong>.
                                Jika sudah ada dokumen <span class="badge badge-success">ACTUAL</span>, revisi itulah yang
                                menjadi acuan scan; bila belum ada, revisi <span class="badge badge-primary">PROFORMA</span>
                                terbaru yang dipakai.
                            </div>
                        </div>
                    </div>

                    <!-- Filter Pencarian -->
                    <div class="card shadow mb-3 {{ $hasSession ? '' : 'd-none' }}">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-filter mr-1"></i> Filter Pencarian
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetFilter">
                                <i class="fas fa-times mr-1"></i> Reset Filter
                            </button>
                        </div>
                        <div class="card-body py-3">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <label class="small font-weight-bold text-gray-700 mb-1">Style</label>
                                    <select id="filterStyle" class="form-control form-control-sm"></select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small font-weight-bold text-gray-700 mb-1">OCF</label>
                                    <select id="filterOcf" class="form-control form-control-sm"></select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small font-weight-bold text-gray-700 mb-1">Destination</label>
                                    <select id="filterDestination" class="form-control form-control-sm"></select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small font-weight-bold text-gray-700 mb-1">Warehouse</label>
                                    <select id="filterWarehouse" class="form-control form-control-sm"></select>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="small font-weight-bold text-gray-700 mb-1">Pencarian Bebas</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        </div>
                                        <input type="text" id="filterSearch" class="form-control"
                                            placeholder="Cari label packing list / batch / buyer...">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2 d-flex align-items-end">
                                    <span class="small text-muted">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Filter bertingkat: pilih <strong>Style</strong> dulu agar pilihan
                                        <strong>OCF</strong>, <strong>Destination</strong>, dan <strong>Warehouse</strong>
                                        menyesuaikan. Pencarian otomatis jalan setelah Anda berhenti mengetik.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <style>
                        #tablePackingLists thead th { white-space: nowrap; }
                        #tablePackingLists td { vertical-align: middle; }
                    </style>

                    <div class="card shadow mb-4 {{ $hasSession ? '' : 'd-none' }}">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-clipboard-list mr-1"></i> Daftar Packing List Aktif
                            </h6>
                            <a href="{{ route('jancode.index') }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus mr-1"></i> Import Baru
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-hover align-middle" id="tablePackingLists" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="35">#</th>
                                            <th class="text-center" width="80">Tipe</th>
                                            <th>Style</th>
                                            <th>OCF</th>
                                            <th>Destination</th>
                                            <th>Warehouse</th>
                                            <th class="text-center" width="80">Revisi</th>
                                            <th class="text-right" width="90">Total Carton</th>
                                            <th class="text-center" width="75">Valid</th>
                                            <th class="text-center" width="75">Broken</th>
                                            <th>Batch Aktif</th>
                                            <th>File</th>
                                            <th width="90">Tanggal</th>
                                            <th class="text-center" width="110">Aksi</th>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            const FILTER_URL = "{{ route('jancode.packingListFilters') }}";

            // ── DataTable ──────────────────────────────────────────────
            const table = $('#tablePackingLists').DataTable({
                processing: true,
                autoWidth: false,
                ajax: {
                    url: "{{ route('jancode.packingLists') }}",
                    type: "GET",
                    dataType: "json",
                    dataSrc: "data",
                    data: function (d) {
                        d.style = $('#filterStyle').val() || '';
                        d.ocf = $('#filterOcf').val() || '';
                        d.destination = $('#filterDestination').val() || '';
                        d.warehouse = $('#filterWarehouse').val() || '';
                        d.q = $('#filterSearch').val() || '';
                    },
                },
                columns: [
                    { data: null, render: (d, t, r, m) => m.row + 1, orderable: false, className: 'text-muted' },
                    {
                        data: 'doc_type',
                        className: 'text-center',
                        render: d => d === 'actual'
                            ? '<span class="badge badge-success">ACTUAL</span>'
                            : '<span class="badge badge-primary">PROFORMA</span>'
                    },
                    {
                        data: 'style_number',
                        render: (d, t, r) => d
                            ? `<strong class="text-dark">${d}</strong><br><small class="text-muted">Buyer: ${r.supplier_name || '-'}</small>`
                            : '<span class="text-muted">-</span>'
                    },
                    { data: 'ocf_number', render: d => d || '<span class="text-muted">-</span>' },
                    { data: 'destination', render: d => d || '<span class="text-muted">-</span>' },
                    { data: 'warehouse', render: d => d ? `<span class="badge badge-light border">${d}</span>` : '<span class="text-muted">-</span>' },
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
                    { data: 'batch_number', render: d => d ? `<code class="small">${d}</code>` : '-' },
                    {
                        data: 'original_filename',
                        render: d => d
                            ? `<span class="text-muted small d-inline-block text-truncate" style="max-width:160px" title="${d}">${d}</span>`
                            : '-'
                    },
                    { data: 'import_date', className: 'text-nowrap' },
                    {
                        data: null, orderable: false, className: 'text-center',
                        render: (d) => {
                            const detail = `{{ url('/jancode/import-batches') }}/${d.id}`;
                            return `<a href="${detail}" class="btn btn-info btn-sm btn-circle" title="Detail & Riwayat Revisi"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('scanner.index') }}?packing_list=${d.id}" class="btn btn-success btn-sm btn-circle ml-1" title="Scan packing list ini"><i class="fas fa-barcode"></i></a>`;
                        }
                    },
                ],
                order: [],
                pageLength: 25,
                language: {
                    processing: '<i class="fas fa-spinner fa-spin mr-1"></i> Memuat data packing list...',
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ packing list',
                    infoEmpty: 'Belum ada data',
                    zeroRecords: 'Tidak ada packing list yang cocok dengan filter',
                    emptyTable: '<div class="text-muted py-3"><i class="fas fa-inbox fa-2x d-block mb-2"></i>Belum ada packing list. Silakan <a href="{{ route('jancode.index') }}">import packing list</a> terlebih dahulu.</div>',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
                },
            });

            // ── Select2 bertingkat (dependent dropdown) ────────────────
            // delay = debounce supaya tidak request tiap ketikan.
            function initFilter(selector, field, placeholder, parents) {
                $(selector).select2({
                    theme: 'bootstrap4',
                    placeholder: placeholder,
                    allowClear: true,
                    width: '100%',
                    minimumInputLength: 0,
                    ajax: {
                        url: FILTER_URL,
                        dataType: 'json',
                        delay: 350,
                        cache: true,
                        data: function (params) {
                            const payload = { field: field, q: params.term || '' };
                            (parents || []).forEach(function (p) {
                                const value = $(p.sel).val();
                                if (value) { payload[p.param] = value; }
                            });
                            return payload;
                        },
                        processResults: function (data) { return { results: data }; },
                    },
                });
            }

            initFilter('#filterStyle', 'style', 'Semua Style', []);
            initFilter('#filterOcf', 'ocf', 'Semua OCF', [{ param: 'style', sel: '#filterStyle' }]);
            initFilter('#filterDestination', 'destination', 'Semua Destination', [
                { param: 'style', sel: '#filterStyle' },
                { param: 'ocf', sel: '#filterOcf' },
            ]);
            initFilter('#filterWarehouse', 'warehouse', 'Semua Warehouse', [
                { param: 'style', sel: '#filterStyle' },
                { param: 'ocf', sel: '#filterOcf' },
                { param: 'destination', sel: '#filterDestination' },
            ]);

            function clearSelect(selector) {
                // trigger 'change.select2' agar UI select2 ikut reset tanpa memicu handler kita.
                $(selector).val(null).trigger('change.select2');
            }

            function reloadTable() {
                table.ajax.reload(null, false);
            }

            // Cascade: ubah parent -> kosongkan anak -> reload tabel
            $('#filterStyle').on('change', function () {
                clearSelect('#filterOcf');
                clearSelect('#filterDestination');
                clearSelect('#filterWarehouse');
                reloadTable();
            });
            $('#filterOcf').on('change', function () {
                clearSelect('#filterDestination');
                clearSelect('#filterWarehouse');
                reloadTable();
            });
            $('#filterDestination').on('change', function () {
                clearSelect('#filterWarehouse');
                reloadTable();
            });
            $('#filterWarehouse').on('change', reloadTable);

            // Debounce pencarian bebas
            let searchTimer = null;
            $('#filterSearch').on('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(reloadTable, 400);
            });

            // Reset semua filter
            $('#btnResetFilter').on('click', function () {
                clearSelect('#filterStyle');
                clearSelect('#filterOcf');
                clearSelect('#filterDestination');
                clearSelect('#filterWarehouse');
                $('#filterSearch').val('');
                reloadTable();
            });
        });
    </script>
</body>
</html>
