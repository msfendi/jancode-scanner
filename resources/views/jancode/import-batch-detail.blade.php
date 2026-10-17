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
                                {{ $batch->group_label ?: 'Detail Packing List' }}
                                @if($batch->is_active)
                                    <span class="badge badge-success align-middle"><i class="fas fa-check mr-1"></i>Aktif</span>
                                @else
                                    <span class="badge badge-secondary align-middle">Superseded</span>
                                @endif
                            </h1>
                            <p class="mb-0 text-muted small">
                                <code>{{ $batch->batch_number }}</code> &mdash;
                                <span class="badge badge-{{ $batch->doc_type === 'actual' ? 'success' : 'primary' }}">{{ $batch->doc_type_label }}</span>
                                <span class="badge badge-dark">Rev.{{ $batch->revision_no }}</span>
                                @if($batch->sheet_name) &middot; sheet: <strong>{{ $batch->sheet_name }}</strong> @endif
                            </p>
                        </div>
                        <div>
                            @if($batch->is_active)
                            <a href="{{ route('scanner.index') }}?packing_list={{ $batch->id }}" class="btn btn-sm btn-success shadow-sm">
                                <i class="fas fa-barcode fa-sm mr-1"></i> Scan Packing List Ini
                            </a>
                            @endif
                            <a href="{{ route('jancode.packingLists') }}" class="btn btn-sm btn-outline-primary shadow-sm ml-1">
                                <i class="fas fa-clipboard-list fa-sm mr-1"></i> Packing List Aktif
                            </a>
                            <a href="{{ route('jancode.importBatches') }}" class="btn btn-sm btn-secondary shadow-sm ml-1">
                                <i class="fas fa-arrow-left fa-sm mr-1"></i> Riwayat Import
                            </a>
                        </div>
                    </div>

                    {{-- Summary Cards --}}
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="card border-left-primary shadow-sm h-100 py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Style / CPO</div>
                                    <div class="h6 mb-0 font-weight-bold">{{ $batch->style_number ?: '-' }}</div>
                                    <div class="text-muted small">{{ $batch->ocf_number ?: '-' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-left-info shadow-sm h-100 py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Destination / WH</div>
                                    <div class="h6 mb-0 font-weight-bold">{{ $batch->destination ?: '-' }}</div>
                                    <div class="text-muted small">{{ $batch->warehouse ?: 'Tanpa warehouse' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-left-success shadow-sm h-100 py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Baris Valid</div>
                                    <div class="h5 mb-0 font-weight-bold text-success">{{ $batch->valid_rows }}</div>
                                    <div class="text-muted small">{{ $batch->total_carton }} carton</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-left-danger shadow-sm h-100 py-2">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Baris Broken</div>
                                    <div class="h5 mb-0 font-weight-bold text-danger">{{ $batch->invalid_rows }}</div>
                                    <div class="text-muted small">tersimpan terpisah</div>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">File</div>
                                    <div class="small text-break">{{ $batch->original_filename ?: '-' }}</div>
                                    <div class="text-muted small">{{ $batch->created_at->format('d/m/Y H:i') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Riwayat Revisi --}}
                    <div class="card shadow-sm mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-layer-group mr-1"></i> Riwayat Revisi Packing List ({{ $revisions->count() }})
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="text-center">Revisi</th>
                                            <th class="text-center">Tipe</th>
                                            <th>Batch No</th>
                                            <th>File / Sheet</th>
                                            <th class="text-center">Valid</th>
                                            <th class="text-center">Broken</th>
                                            <th class="text-center">Carton</th>
                                            <th class="text-center">Status</th>
                                            <th>Waktu</th>
                                            <th width="80"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($revisions as $rev)
                                        <tr class="{{ $rev->id === $batch->id ? 'table-primary' : '' }}">
                                            <td class="text-center"><span class="badge badge-dark">Rev.{{ $rev->revision_no }}</span></td>
                                            <td class="text-center">
                                                <span class="badge badge-{{ $rev->doc_type === 'actual' ? 'success' : 'primary' }}">{{ strtoupper($rev->doc_type) }}</span>
                                            </td>
                                            <td><code>{{ $rev->batch_number }}</code></td>
                                            <td><small class="text-muted">{{ $rev->original_filename ?: '-' }}</small></td>
                                            <td class="text-center text-success font-weight-bold">{{ $rev->valid_rows }}</td>
                                            <td class="text-center text-danger font-weight-bold">{{ $rev->invalid_rows }}</td>
                                            <td class="text-center font-weight-bold">{{ $rev->total_carton }}</td>
                                            <td class="text-center">
                                                @if($rev->is_active)
                                                    <span class="badge badge-success">Aktif</span>
                                                @else
                                                    <span class="badge badge-secondary">Superseded</span>
                                                @endif
                                            </td>
                                            <td><small class="text-muted">{{ $rev->created_at ? $rev->created_at->format('d/m/Y H:i') : '-' }}</small></td>
                                            <td class="text-center">
                                                @if($rev->id !== $batch->id)
                                                    <a href="{{ route('jancode.importBatchDetail', $rev->id) }}" class="btn btn-outline-info btn-sm btn-circle" title="Lihat revisi ini">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                @else
                                                    <span class="badge badge-light border">Sedang dilihat</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
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
                                <i class="fas fa-check-circle mr-1"></i> Baris Valid / SOLID ({{ $batch->valid_rows }})
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" data-toggle="tab" href="#tabFailed" role="tab">
                                <i class="fas fa-times-circle mr-1"></i> Baris Broken / Skipped ({{ $batch->invalid_rows }})
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
                                                    <th>Carton No</th>
                                                    <th>Color</th>
                                                    <th>Size</th>
                                                    <th>JAN Code</th>
                                                    <th class="text-right">Carton</th>
                                                    <th class="text-right">Pcs/Ctn</th>
                                                    <th class="text-right">Total Pcs</th>
                                                    <th class="text-right">Received</th>
                                                    <th class="text-right">Balance</th>
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
                                        Baris berikut <strong>tidak masuk tabel utama</strong> karena qty carton-nya
                                        di-merge (broken), ASSORT, bukan SOLID, atau tidak lengkap. Data tetap tersimpan
                                        sebagai referensi di tabel terpisah.
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

            const tableItems = $('#tableItems').DataTable({
                processing: true,
                ajax: { url: baseUrl + '?type=items', dataSrc: 'data' },
                columns: [
                    { data: null, render: (d, t, r, m) => m.row + 1, orderable: false },
                    { data: 'country' },
                    { data: 'buyer' },
                    { data: 'ocf' },
                    { data: 'style' },
                    { data: null, render: d => {
                        if (!d.carton_no_from) return '-';
                        return d.carton_no_to && d.carton_no_to !== d.carton_no_from
                            ? `${d.carton_no_from} - ${d.carton_no_to}` : d.carton_no_from;
                    } },
                    { data: 'color', render: d => `<small>${d}</small>` },
                    { data: 'size', render: d => `<span class="badge badge-primary">${d}</span>` },
                    { data: 'jancode', render: d => `<code>${d}</code>` },
                    { data: 'packing_list_qty_carton', className: 'text-right font-weight-bold' },
                    { data: 'qty_per_carton', className: 'text-right' },
                    { data: 'total_qty_pcs', className: 'text-right', render: d => (d || 0).toLocaleString() },
                    { data: 'receive_qty_carton', className: 'text-right text-success font-weight-bold' },
                    { data: 'balance', className: 'text-right font-weight-bold', render: d => d > 0 ? `<span class="text-warning">${d}</span>` : '<span class="text-success">0</span>' },
                ],
                pageLength: 50,
            });

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

            // Muat tabel broken saat tab dibuka
            $('a[href="#tabFailed"]').on('shown.bs.tab', function () {
                tableFailed.ajax.reload(null, false);
            });
        });
    </script>
</body>
</html>
