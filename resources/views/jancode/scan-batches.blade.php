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
                            <h1 class="h4 mb-0 text-gray-800">
                                <i class="fas fa-history text-primary mr-1"></i> Riwayat Batch Scan Jancode
                            </h1>
                            <p class="mb-0 text-muted small">
                                Daftar sesi <strong>submit</strong> hasil scanning karton per kedatangan (partial scan) — lengkap dengan kode surat jalan.
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
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-filter mr-1"></i> Filter Riwayat
                            </h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="row align-items-end">
                                <div class="col-md-4 mb-2">
                                    <label for="filterSession" class="small font-weight-bold mb-1">Session</label>
                                    <select id="filterSession" class="form-control form-control-sm">
                                        <option value="">Semua Session</option>
                                        @foreach($sessions as $s)
                                            <option value="{{ $s->id }}">
                                                {{ $s->code }}{{ (string) $currentSessionId === (string) $s->id ? ' (aktif)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="filterDateFrom" class="small font-weight-bold mb-1">Tanggal Scan (Dari)</label>
                                    <input type="date" id="filterDateFrom" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="filterDateTo" class="small font-weight-bold mb-1">Tanggal Scan (Sampai)</label>
                                    <input type="date" id="filterDateTo" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <button class="btn btn-sm btn-primary btn-block" id="btnFilter">
                                        <i class="fas fa-search mr-1"></i> Terapkan
                                    </button>
                                    <button class="btn btn-sm btn-link btn-block text-muted" id="btnResetFilter">
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6 col-xl-3 mb-3 mb-xl-0">
                            <div class="card border-0 shadow-sm h-100 batch-stat-card">
                                <div class="card-body py-3 d-flex align-items-center">
                                    <div class="batch-stat-icon" style="background:#eef2ff;color:#4e73df;">
                                        <i class="fas fa-file-invoice"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="batch-stat-value" id="statSubmit">0</div>
                                        <div class="batch-stat-label">Sesi Submit</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-xl-3 mb-3 mb-xl-0">
                            <div class="card border-0 shadow-sm h-100 batch-stat-card">
                                <div class="card-body py-3 d-flex align-items-center">
                                    <div class="batch-stat-icon" style="background:#e8faf2;color:#1cc88a;">
                                        <i class="fas fa-barcode"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="batch-stat-value" id="statScanned">0</div>
                                        <div class="batch-stat-label">Karton Di-Scan</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-xl-3 mb-3 mb-xl-0">
                            <div class="card border-0 shadow-sm h-100 batch-stat-card">
                                <div class="card-body py-3 d-flex align-items-center">
                                    <div class="batch-stat-icon" style="background:#e8f7fb;color:#36b9cc;">
                                        <i class="fas fa-boxes"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="batch-stat-value" id="statReceive">0</div>
                                        <div class="batch-stat-label">Akumulasi Receive</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-xl-3 mb-3 mb-xl-0">
                            <div class="card border-0 shadow-sm h-100 batch-stat-card">
                                <div class="card-body py-3 d-flex align-items-center">
                                    <div class="batch-stat-icon" style="background:#fff4e5;color:#f6a623;">
                                        <i class="fas fa-balance-scale"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="batch-stat-value" id="statBalance">0</div>
                                        <div class="batch-stat-label">Sisa Balance</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="d-flex align-items-center">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-history mr-1"></i> Daftar Sesi Submit Batch Scan
                                    </h6>
                                    <span class="badge badge-light border ml-2" id="batchCountBadge">0 baris</span>
                                </div>
                                <div class="small text-muted mt-1">Klik judul kolom untuk mengurutkan data.</div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" id="btnRefresh">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle batch-table" id="tableScanBatches" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="40">#</th>
                                            <th>Waktu Submit</th>
                                            <th>Session</th>
                                            <th>Batch No</th>
                                            <th>Surat Jalan</th>
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

    <style>
        .batch-stat-card {
            border-radius: .7rem;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .batch-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 .35rem .9rem rgba(58, 59, 69, .12) !important;
        }
        .batch-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex: 0 0 44px;
        }
        .batch-stat-value {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.1;
            color: #2e3a59;
        }
        .batch-stat-label {
            font-size: .68rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #858796;
            font-weight: 800;
        }
        .batch-table thead th {
            border-top: 0;
            border-bottom: 1px solid #e3e6f0;
            background: #f8f9fc;
            color: #858796;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .batch-table tbody td {
            border-top: 1px solid #f0f2f8;
            vertical-align: middle;
        }
        .batch-table tbody tr:hover {
            background: #f8f9fc;
        }
    </style>

    <script>
        $(document).ready(function () {
            function updateBatchSummary(api) {
                const rows = api.rows({ search: 'applied' }).data().toArray();
                const sum = (key) => rows.reduce((total, row) => total + (Number(row[key]) || 0), 0);

                $('#statSubmit').text(rows.length.toLocaleString('id-ID'));
                $('#statScanned').text(sum('scanned_qty').toLocaleString('id-ID'));
                $('#statReceive').text(sum('receive_qty').toLocaleString('id-ID'));
                $('#statBalance').text(sum('balance').toLocaleString('id-ID'));
                $('#batchCountBadge').text(rows.length.toLocaleString('id-ID') + ' baris');
            }

            const table = $('#tableScanBatches').DataTable({
                processing: true,
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
                ajax: {
                    url: "{{ route('scanner.batches') }}",
                    type: "GET",
                    dataType: "json",
                    dataSrc: "data",
                    data: function (d) {
                        d.session_id = $('#filterSession').val() || '';
                        d.date_from = $('#filterDateFrom').val() || '';
                        d.date_to = $('#filterDateTo').val() || '';
                    }
                },
                columns: [
                    { data: null, render: (d, t, r, m) => m.row + 1, orderable: false },
                    { data: 'created_at', render: d => `<small class="text-muted">${d}</small>` },
                    { data: 'session_code', render: d => `<span class="badge badge-light border">${d || '-'}</span>` },
                    { data: 'batch_no', render: d => `<span class="badge badge-dark font-mono px-2 py-1">${d}</span>` },
                    { data: 'surat_jalan', render: d => d && d !== '-' ? `<span class="badge badge-info px-2 py-1">${d}</span>` : '<span class="text-muted">—</span>' },
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
                columnDefs: [
                    { orderable: false, targets: [0] }
                ],
                drawCallback: function () {
                    updateBatchSummary(this.api());
                },
                language: {
                    processing: 'Memuat data...',
                    search: 'Cari cepat:',
                    lengthMenu: 'Tampilkan _MENU_ baris',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
                    infoEmpty: 'Tidak ada data',
                    infoFiltered: '(difilter dari _MAX_ total baris)',
                    zeroRecords: 'Tidak ada riwayat batch scan yang cocok',
                    paginate: {
                        first: '«',
                        last: '»',
                        next: '›',
                        previous: '‹'
                    }
                }
            });

            $('#btnFilter').click(function () {
                table.ajax.reload();
            });

            $('#btnResetFilter').click(function () {
                $('#filterSession').val('');
                $('#filterDateFrom').val('');
                $('#filterDateTo').val('');
                table.ajax.reload();
            });

            // Enter pada filter tanggal langsung menerapkan filter.
            $('#filterDateFrom, #filterDateTo').on('change', function () {
                table.ajax.reload();
            });

            $('#filterSession').on('change', function () {
                table.ajax.reload();
            });

            $('#btnRefresh').click(function () {
                table.ajax.reload(null, false);
            });
        });
    </script>
</body>
</html>
