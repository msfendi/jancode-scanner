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
                            <h1 class="h3 mb-0 text-gray-800">Detail Batch Import</h1>
                            <p class="mb-0 text-muted small">
                                <code>{{ $batch->batch_number }}</code> &mdash; {{ $batch->batch_name }}
                            </p>
                        </div>
                        <a href="{{ route('jancode.importBatches') }}" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm mr-1"></i> Kembali
                        </a>
                    </div>

                    {{-- Summary Cards --}}
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="card border-left-primary shadow-sm h-100 py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">OCF / Supplier</div>
                                    <div class="h6 mb-0 font-weight-bold">{{ $batch->ocf_number ?: '-' }}</div>
                                    <div class="text-muted small">{{ $batch->supplier_name ?: '-' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-left-info shadow-sm h-100 py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">File</div>
                                    <div class="small text-break">{{ $batch->original_filename ?: '-' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-left-success shadow-sm h-100 py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Valid / Processed</div>
                                    <div class="h5 mb-0 font-weight-bold text-success">{{ $batch->valid_rows }} / {{ $batch->processed_items }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-left-danger shadow-sm h-100 py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Gagal / Skipped</div>
                                    <div class="h5 mb-0 font-weight-bold text-danger">{{ $batch->invalid_rows }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-left-secondary shadow-sm h-100 py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Status</div>
                                    @php
                                        $badgeClass = match($batch->status) {
                                            'completed' => 'badge-success',
                                            'partial' => 'badge-warning text-dark',
                                            'failed' => 'badge-danger',
                                            default => 'badge-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-3 py-2">{{ ucfirst($batch->status) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-left-warning shadow-sm h-100 py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Imported By</div>
                                    <div class="small font-weight-bold">{{ $batch->importedBy ? $batch->importedBy->name : '-' }}</div>
                                    <div class="text-muted small">{{ $batch->created_at->format('d/m/Y H:i') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($batch->import_notes)
                    <div class="alert alert-light border mb-4">
                        <i class="fas fa-info-circle mr-1 text-info"></i>
                        <strong>Catatan Import:</strong> {{ $batch->import_notes }}
                    </div>
                    @endif

                    {{-- Tabs --}}
                    <ul class="nav nav-tabs font-weight-bold mb-0" id="batchDetailTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active text-success" data-toggle="tab" href="#tabItems" role="tab">
                                <i class="fas fa-check-circle mr-1"></i> Item Valid ({{ $batch->processed_items }})
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" data-toggle="tab" href="#tabFailed" role="tab">
                                <i class="fas fa-times-circle mr-1"></i> Baris Gagal / Skipped ({{ $batch->invalid_rows }})
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        {{-- Tab Items Valid --}}
                        <div class="tab-pane fade show active" id="tabItems">
                            <div class="card shadow-sm border-0 rounded-0 rounded-bottom">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm table-hover" id="tableItems" width="100%">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Country</th>
                                                    <th>Buyer</th>
                                                    <th>OCF</th>
                                                    <th>Style</th>
                                                    <th>Color</th>
                                                    <th>Size</th>
                                                    <th>JAN Code</th>
                                                    <th class="text-right">Carton</th>
                                                    <th class="text-right">Pcs/Ctn</th>
                                                    <th class="text-right">Total Pcs</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tab Failed Rows --}}
                        <div class="tab-pane fade" id="tabFailed">
                            <div class="card shadow-sm border-0 rounded-0 rounded-bottom">
                                <div class="card-body">
                                    <div class="alert alert-warning border-left-warning mb-3">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Baris-baris berikut tidak berhasil diimport karena tidak memenuhi kriteria (broken carton, no JAN, ASSORT, dll).
                                        Record ini disimpan sebagai referensi.
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm table-hover" id="tableFailed" width="100%">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Row Excel</th>
                                                    <th>Reason</th>
                                                    <th>Keterangan Alasan</th>
                                                    <th>JAN Code</th>
                                                    <th>Color</th>
                                                    <th>Size</th>
                                                    <th>Keterangan</th>
                                                    <th class="text-center">Carton</th>
                                                    <th class="text-center">Merge?</th>
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
                </div>
            </div>
            @include('layout.footer')

    <!-- Page level plugins -->
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

    <script>
        $(document).ready(function () {
            const batchId = {{ $batch->id }};
            const baseUrl = "{{ route('jancode.importBatchDetail', ':id') }}".replace(':id', batchId);

            const reasonBadges = {
                'MERGED_CARTON': '<span class="badge badge-warning text-dark">CARTON MERGE</span>',
                'NO_JAN':        '<span class="badge badge-danger">NO JAN</span>',
                'NO_CARTON':     '<span class="badge badge-danger">NO CARTON</span>',
                'NO_SIZE':       '<span class="badge badge-danger">NO SIZE</span>',
                'MULTI_SIZE':    '<span class="badge badge-danger">MULTI SIZE</span>',
                'ASSORT':        '<span class="badge badge-secondary">ASSORT</span>',
                'SUMMARY_ROW':   '<span class="badge badge-light">SUMMARY ROW</span>',
                'OTHER':         '<span class="badge badge-secondary">OTHER</span>',
            };

            // Items table
            const tableItems = $('#tableItems').DataTable({
                processing: true,
                ajax: { url: baseUrl + '?type=items', dataSrc: 'data' },
                columns: [
                    { data: null, render: (d, t, r, m) => m.row + 1, orderable: false },
                    { data: 'country' },
                    { data: 'buyer' },
                    { data: 'ocf' },
                    { data: 'style' },
                    { data: 'color', render: d => `<small>${d}</small>` },
                    { data: 'size', render: d => `<span class="badge badge-primary">${d}</span>` },
                    { data: 'jancode', render: d => `<code>${d}</code>` },
                    { data: 'packing_list_qty_carton', className: 'text-right font-weight-bold' },
                    { data: 'qty_per_carton', className: 'text-right' },
                    { data: 'total_qty_pcs', className: 'text-right', render: d => (d || 0).toLocaleString() },
                ],
                pageLength: 50,
            });

            // Failed rows table
            const tableFailed = $('#tableFailed').DataTable({
                processing: true,
                ajax: { url: baseUrl + '?type=failed', dataSrc: 'data' },
                columns: [
                    { data: null, render: (d, t, r, m) => m.row + 1, orderable: false },
                    { data: 'source_row', render: d => `<strong>${d}</strong>` },
                    { data: 'reason_code', render: d => reasonBadges[d] || d },
                    { data: 'reason_label', render: d => `<small class="text-muted">${d}</small>` },
                    { data: 'jan_code', render: d => d ? `<code>${d}</code>` : '-' },
                    { data: 'color', render: d => d || '-' },
                    { data: 'size', render: d => d || '-' },
                    { data: 'keterangan', render: d => d || '-' },
                    { data: 'total_carton', className: 'text-center', render: d => d || '-' },
                    { data: 'carton_merged', className: 'text-center', render: d => d ? '<span class="badge badge-warning text-dark">Ya</span>' : '<span class="badge badge-light">Tidak</span>' },
                    { data: 'notes', render: d => d ? `<small class="text-muted font-italic">${d}</small>` : '-' },
                ],
                pageLength: 50,
            });

            // Load failed table when tab shown
            $('a[href="#tabFailed"]').on('shown.bs.tab', function () {
                tableFailed.ajax.reload(null, false);
            });
        });
    </script>
</body>
</html>
