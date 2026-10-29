<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<link href="{{ asset('vendor/jquery/select2-bootstrap4.min.css') }}" rel="stylesheet" />

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
                                Klik <i class="fas fa-eye"></i> untuk melihat riwayat revisi &amp; baris broken,
                                klik <i class="fas fa-list text-primary"></i> untuk daftar jancode, dan
                                <i class="fas fa-trash text-danger"></i> untuk menghapus packing list.
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
                    @elseif ($currentSession->isClosed())
                        <div class="alert alert-warning border-left-warning shadow-sm mb-3 py-2">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <div class="small mb-2 mb-md-0">
                                    <i class="fas fa-lock mr-1"></i>
                                    Session <strong>{{ $currentSession->code }}</strong> sudah ditutup
                                    ({{ $currentSession->group_label }}). Data tetap bisa dilihat, tetapi
                                    import &amp; scan baru diblokir.
                                </div>
                                <form method="POST" action="{{ route('session.reopen', $currentSession) }}" class="mb-0"
                                    onsubmit="return confirm('Buka kembali session {{ $currentSession->code }} untuk melanjutkan pekerjaan?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success font-weight-bold">
                                        <i class="fas fa-undo mr-1"></i> Buka Kembali Session
                                    </button>
                                </form>
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
                                <hr class="my-2">
                                Revisi pertama import ditampilkan sebagai <span class="badge badge-success">Original</span>.
                                Tombol <span class="badge badge-success"><i class="fas fa-flag-checkered mr-1"></i>Finish</span>
                                hanya aktif bila <strong>semua JAN code sudah lengkap (balance 0)</strong>; setelah di-Finish
                                tersedia report <strong>PDF</strong> &amp; <strong>Excel</strong>.
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
                                            <th class="text-center" width="85">Jancode</th>
                                            <th class="text-center" width="175">Finish &amp; Report</th>
                                            <th class="text-center" width="150">Aksi</th>
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

    <!-- Modal daftar jancode per packing list -->
    <div class="modal fade" id="jancodeListModal" tabindex="-1" role="dialog" aria-labelledby="jlTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <div>
                        <h6 class="modal-title mb-0" id="jlTitle">
                            <i class="fas fa-list mr-1"></i> Daftar Jancode
                        </h6>
                        <small class="text-muted" id="jlSubtitle">-</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-2">
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <div class="d-flex align-items-center mr-3 mb-1">
                            <label for="jlRevision" class="small font-weight-bold mb-0 mr-2">Revisi</label>
                            <select id="jlRevision" class="form-control form-control-sm d-none"
                                style="min-width: 260px; max-width: 100%"></select>
                        </div>
                        <div class="ml-auto mb-1">
                            <input type="text" id="jlSearch" class="form-control form-control-sm"
                                placeholder="Cari JAN code / color / size / carton..."
                                style="min-width: 260px; max-width: 100%">
                        </div>
                    </div>

                    <div class="alert alert-light border py-2 mb-2 small" id="jlSummary">-</div>

                    <div class="table-responsive" style="max-height: 55vh">
                        <table class="table table-sm table-bordered table-hover mb-0" id="jlTable" style="font-size: 12px">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40" class="text-center">#</th>
                                    <th>JAN Code</th>
                                    <th>Color</th>
                                    <th width="60" class="text-center">Size</th>
                                    <th>Carton No</th>
                                    <th width="75" class="text-right">Qty/Ctn</th>
                                    <th width="90" class="text-right">Carton PL</th>
                                    <th width="85" class="text-right">Receive</th>
                                    <th width="75" class="text-right">Balance</th>
                                    <th width="110" class="text-right">Total Pcs</th>
                                    <th width="95" class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div id="jlEmpty" class="text-center text-muted py-4 d-none">Belum ada jancode.</div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/sweetalert/sweetalert.all.js') }}"></script>
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            const FILTER_URL = "{{ route('jancode.packingListFilters') }}";
            const JANCODE_URL = "{{ route('jancode.packingListJancodes', ['packingList' => '__id__']) }}";
            const DESTROY_URL = "{{ route('jancode.packingListDestroy', ['packingList' => '__id__']) }}";
            const FINISH_URL = "{{ route('jancode.packingListFinish', ['packingList' => '__id__']) }}";
            const CLEAR_PALLETS_URL = "{{ route('jancode.packingListClearPallets', ['packingList' => '__id__']) }}";
            const REPORT_PDF_URL = "{{ route('jancode.packingListReportPdf', ['packingList' => '__id__']) }}";
            const REPORT_EXCEL_URL = "{{ route('jancode.packingListReportExcel', ['packingList' => '__id__']) }}";
            const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

            // Escape teks untuk dimasukkan ke HTML / atribut.
            function esc(value) {
                return $('<div>').text(value === null || value === undefined ? '' : value).html();
            }

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
                        render: d => {
                            const isOriginal = Number(d.revision_no) === 1;
                            const badge = isOriginal
                                ? '<span class="badge badge-success">Original</span>'
                                : `<span class="badge badge-dark">${d.revision_label || ('Rev.' + d.revision_no)}</span>`;

                            return badge +
                                `<br><small class="text-muted">${d.revision_count > 1 ? d.revision_count + ' revisi' : 'Revisi awal'}</small>`;
                        }
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
                        data: null, orderable: false, className: 'text-center', width: '85',
                        render: (d) => {
                            if (!d.packing_list_id) return '<span class="text-muted">-</span>';
                            return `<button type="button" class="btn btn-link btn-sm p-0 font-weight-bold btn-jancodes"
                                        data-pl="${d.packing_list_id}"
                                        data-batch="${d.id}"
                                        data-label="${esc(d.group_label || d.batch_number)}"
                                        title="Lihat daftar jancode">
                                        <i class="fas fa-list mr-1"></i>${d.jancode_count}
                                    </button>`;
                        }
                    },
                    {
                        data: null, orderable: false, className: 'text-center text-nowrap', width: '175',
                        render: (d) => {
                            if (!d.packing_list_id) return '<span class="text-muted">-</span>';

                            const plId = d.packing_list_id;
                            const balance = Number(d.balance_total || 0);

                            if (d.is_finished) {
                                return `<span class="badge badge-success px-2 py-1" title="Finished ${d.finished_at || '-'} oleh ${d.finished_by || '-'}">
                                        <i class="fas fa-check-circle mr-1"></i>Finished
                                    </span>
                                    <div class="mt-1">
                                        <a href="${REPORT_PDF_URL.replace('__id__', plId)}" target="_blank"
                                            class="btn btn-sm btn-outline-danger btn-circle" title="Download report PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="${REPORT_EXCEL_URL.replace('__id__', plId)}" target="_blank"
                                            class="btn btn-sm btn-outline-success btn-circle ml-1" title="Download report Excel">
                                            <i class="fas fa-file-excel"></i>
                                        </a>
                                    </div>`;
                            }

                            if (d.can_finish) {
                                return `<button type="button" class="btn btn-sm btn-success btn-finish-pl"
                                        data-pl="${plId}"
                                        data-label="${esc(d.group_label || d.batch_number)}"
                                        data-rows="${d.row_count || 0}"
                                        data-carton="${d.packing_total || 0}"
                                        title="Semua JAN code sudah lengkap (balance 0) — tandai packing list ini selesai">
                                        <i class="fas fa-flag-checkered mr-1"></i>Finish
                                    </button>`;
                            }

                            return `<button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                        title="Belum bisa di-Finish: masih ada ${balance} karton balance">
                                        <i class="fas fa-lock mr-1"></i>Sisa ${balance} ctn
                                    </button>`;
                        }
                    },
                    {
                        data: null, orderable: false, className: 'text-center text-nowrap', width: '150',
                        render: (d) => {
                            const detail = `{{ url('/jancode/import-batches') }}/${d.id}`;
                            const scanTitle = d.is_finished
                                ? 'Packing list sudah Finished'
                                : 'Scan packing list ini';
                            const scanClass = d.is_finished
                                ? 'btn btn-secondary btn-sm btn-circle ml-1 disabled'
                                : 'btn btn-success btn-sm btn-circle ml-1';

                            let html = `<a href="${detail}" class="btn btn-info btn-sm btn-circle" title="Detail & Riwayat Revisi"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('scanner.index') }}?packing_list=${d.id}" class="${scanClass}" title="${scanTitle}"><i class="fas fa-barcode"></i></a>`;

                            if (d.packing_list_id) {
                                html += `<button type="button" class="btn btn-danger btn-sm btn-circle ml-1 btn-delete-pl"
                                    data-pl="${d.packing_list_id}"
                                    data-label="${esc(d.group_label || d.batch_number)}"
                                    data-revisions="${d.revision_count}"
                                    data-jancodes="${d.jancode_total}"
                                    title="Hapus packing list"><i class="fas fa-trash"></i></button>`;
                            }

                            return html;
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

            // ── Daftar jancode per packing list ────────────────────────
            let jlPackingListId = null;
            let jlRows = [];
            let jlRequestSeq = 0;

            function openJancodeModal(plId, batchId, label) {
                jlPackingListId = plId;
                jlRows = [];

                $('#jlTitle').html('<i class="fas fa-list mr-1"></i> Daftar Jancode');
                $('#jlSubtitle').text(label || '');
                $('#jlSummary').html('<span class="text-muted">Memuat data...</span>');
                $('#jlTable tbody').empty();
                $('#jlEmpty').addClass('d-none');
                $('#jlRevision').empty().addClass('d-none');
                $('#jlSearch').val('');

                $('#jancodeListModal').modal('show');
                loadJancodes(batchId);
            }

            function loadJancodes(batchId) {
                const url = JANCODE_URL.replace('__id__', jlPackingListId);
                const seq = ++jlRequestSeq;

                $.getJSON(url, batchId ? { batch_id: batchId } : {})
                    .done(function (res) {
                        if (seq !== jlRequestSeq) return;

                        const pl = res.packing_list || {};
                        const sessionText = pl.session ? ' · ' + pl.session : '';

                        $('#jlSubtitle').text((pl.label || '-') + sessionText);

                        const revisions = res.revisions || [];
                        const $rev = $('#jlRevision');

                        $rev.empty();
                        if (revisions.length > 1) {
                            revisions.forEach(function (r) {
                                const label = (r.revision_label || ('Rev.' + r.revision_no)) + ' · ' +
                                    String(r.doc_type || '').toUpperCase() + ' · ' +
                                    r.batch_number + ' (' + r.jancode_count + ' JAN)' +
                                    (r.is_active ? ' · aktif' : '');

                                $rev.append(new Option(
                                    label,
                                    r.id,
                                    false,
                                    String(r.id) === String(res.selected_batch_id)
                                ));
                            });
                            $rev.removeClass('d-none');
                        } else {
                            $rev.addClass('d-none');
                        }

                        jlRows = res.data || [];
                        renderJancodes();

                        const s = res.summary || {};
                        $('#jlSummary').html(
                            '<i class="fas fa-boxes mr-1"></i>' +
                            '<strong>' + (s.rows || 0) + '</strong> jancode · ' +
                            '<strong>' + (s.total_carton || 0) + '</strong> karton · ' +
                            '<strong>' + (s.total_pcs || 0).toLocaleString('id-ID') + '</strong> pcs' +
                            '<span class="text-muted ml-2">(received: ' + (s.received || 0) +
                            ', balance: ' + (s.balance || 0) + ')</span>'
                        );
                    })
                    .fail(function (xhr) {
                        if (seq !== jlRequestSeq) return;

                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ||
                            'Gagal memuat daftar jancode.';

                        $('#jlSummary').html('<span class="text-danger">' + esc(msg) + '</span>');
                        $('#jlEmpty').removeClass('d-none').text(msg);
                    });
            }

            function renderJancodes() {
                const term = ($('#jlSearch').val() || '').toString().toLowerCase().trim();

                const rows = term
                    ? jlRows.filter(function (r) {
                        return [r.jancode, r.color, r.size, r.carton_no_from, r.carton_no_to, r.country]
                            .join(' ')
                            .toLowerCase()
                            .indexOf(term) !== -1;
                    })
                    : jlRows;

                const $tbody = $('#jlTable tbody').empty();

                rows.forEach(function (r, idx) {
                    const receive = Number(r.receive_qty_carton || 0);
                    const balance = Number(r.balance || 0);

                    let status = '<span class="badge badge-secondary">Belum</span>';
                    if (balance <= 0) {
                        status = '<span class="badge badge-success">Lengkap</span>';
                    } else if (receive > 0) {
                        status = '<span class="badge badge-warning text-dark">Sebagian</span>';
                    }

                    let carton = '-';
                    if (r.carton_no_from || r.carton_no_to) {
                        carton = (!r.carton_no_to || r.carton_no_from === r.carton_no_to)
                            ? (r.carton_no_from || '-')
                            : (r.carton_no_from + '–' + r.carton_no_to);
                    }

                    $tbody.append(
                        '<tr>' +
                        '<td class="text-center text-muted">' + (idx + 1) + '</td>' +
                        '<td><code>' + esc(r.jancode || '-') + '</code></td>' +
                        '<td>' + esc(r.color || '-') + '</td>' +
                        '<td class="text-center">' + esc(r.size || '-') + '</td>' +
                        '<td>' + esc(carton) + '</td>' +
                        '<td class="text-right">' + (r.qty_per_carton || 0) + '</td>' +
                        '<td class="text-right font-weight-bold">' + (r.packing_list_qty_carton || 0) + '</td>' +
                        '<td class="text-right text-success">' + receive + '</td>' +
                        '<td class="text-right">' + balance + '</td>' +
                        '<td class="text-right">' + (r.total_qty_pcs || 0).toLocaleString('id-ID') + '</td>' +
                        '<td class="text-center">' + status + '</td>' +
                        '</tr>'
                    );
                });

                if (!rows.length) {
                    $('#jlEmpty')
                        .removeClass('d-none')
                        .text(term ? 'Tidak ada jancode yang cocok.' : 'Belum ada jancode pada revisi ini.');
                } else {
                    $('#jlEmpty').addClass('d-none');
                }
            }

            $('#jlSearch').on('input', renderJancodes);
            $('#jlRevision').on('change', function () {
                loadJancodes($(this).val());
            });

            // ── Hapus packing list ─────────────────────────────────────
            function deletePackingList(plId, label, revisions, jancodes) {
                const html = '<div class="text-left small">' +
                    '<p class="mb-1"><strong>' + esc(label) + '</strong></p>' +
                    '<p class="mb-1 text-danger">Semua data berikut akan dihapus permanen:</p>' +
                    '<ul class="mb-0 pl-3">' +
                    '<li>' + esc(String(revisions)) + ' revisi import</li>' +
                    '<li>' + esc(String(jancodes)) + ' jancode</li>' +
                    '<li>seluruh scan log, riwayat scan, penempatan pallet, dan karton keluar terkait</li>' +
                    '</ul></div>';

                function run() {
                    $.ajax({
                        url: DESTROY_URL.replace('__id__', plId),
                        type: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                            'Accept': 'application/json'
                        },
                    }).done(function (res) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Terhapus',
                                text: res.message || 'Packing list berhasil dihapus.',
                                timer: 2800,
                                showConfirmButton: false,
                            });
                        } else {
                            alert(res.message || 'Packing list berhasil dihapus.');
                        }

                        table.ajax.reload(null, false);
                    }).fail(function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ||
                            'Gagal menghapus packing list.';

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
                        } else {
                            alert(msg);
                        }
                    });
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Hapus packing list?',
                        html: html,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, hapus',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#e74a3b',
                        focusCancel: true,
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            run();
                        }
                    });
                } else if (confirm('Hapus packing list ini beserta seluruh revisi, jancode, scan, dan penempatan palletnya?')) {
                    run();
                }
            }

            $('#tablePackingLists').on('click', '.btn-jancodes', function () {
                openJancodeModal(
                    $(this).data('pl'),
                    $(this).data('batch'),
                    $(this).data('label')
                );
            });

            $('#tablePackingLists').on('click', '.btn-delete-pl', function () {
                deletePackingList(
                    $(this).data('pl'),
                    $(this).data('label'),
                    $(this).data('revisions'),
                    $(this).data('jancodes')
                );
            });

            // ── Finish packing list ─────────────────────────────────────
            function finishPackingList(plId, label, rows, carton) {
                const html = '<div class="text-left small">' +
                    '<p class="mb-1"><strong>' + esc(label) + '</strong></p>' +
                    '<p class="mb-1">Seluruh JAN code pada revisi aktif sudah lengkap (balance 0 karton).</p>' +
                    '<ul class="mb-0 pl-3">' +
                    '<li>' + esc(String(rows)) + ' JAN code</li>' +
                    '<li>' + esc(String(carton)) + ' karton packing list</li>' +
                    '</ul>' +
                    '<p class="mb-0 mt-2 text-muted">Setelah di-Finish, report PDF &amp; Excel bisa diunduh.</p></div>';

                function clearPallets(plId, pallets) {
                    return $.ajax({
                        url: CLEAR_PALLETS_URL.replace('__id__', plId),
                        type: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                            'Accept': 'application/json'
                        },
                    });
                }

                function askToClearPallets(plId, pallets) {
                    if (!pallets || !pallets.length) {
                        table.ajax.reload(null, false);
                        return;
                    }

                    const list = pallets.map(function (p) {
                        return '<li><strong>' + esc(p.code) + '</strong> <span class="text-muted">(' +
                            esc(String(p.cartons)) + ' karton)</span></li>';
                    }).join('');

                    const html = '<div class="text-left small">' +
                        '<p class="mb-2">Packing list sudah <strong>Finished</strong>. Apakah Anda ingin mengosongkan pallet yang dituju?</p>' +
                        '<ul class="mb-2 pl-3">' + list + '</ul>' +
                        '<p class="mb-0 text-muted">Jika Ya, karton akan ditarik dari pallet dan pallet yang kosong bisa dipakai lagi.</p>' +
                        '</div>';

                    if (typeof Swal === 'undefined') {
                        if (!confirm('Kosongkan pallet yang dituju?')) {
                            table.ajax.reload(null, false);
                            return;
                        }

                        clearPallets(plId, pallets).done(function (res) {
                            alert(res.message || 'Pallet berhasil dikosongkan.');
                            table.ajax.reload(null, false);
                        }).fail(function (xhr) {
                            alert((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal mengosongkan pallet.');
                            table.ajax.reload(null, false);
                        });
                        return;
                    }

                    Swal.fire({
                        icon: 'question',
                        title: 'Kosongkan pallet?',
                        html: html,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, kosongkan',
                        cancelButtonText: 'Biarkan',
                        confirmButtonColor: '#e74a3b',
                        focusCancel: true,
                    }).then(function (result) {
                        if (!result.isConfirmed) {
                            table.ajax.reload(null, false);
                            return;
                        }

                        clearPallets(plId, pallets).done(function (res) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Pallet dikosongkan',
                                text: res.message || 'Karton berhasil ditarik dari pallet.',
                                timer: 2600,
                                showConfirmButton: false,
                            });
                            table.ajax.reload(null, false);
                        }).fail(function (xhr) {
                            const msg = (xhr.responseJSON && xhr.responseJSON.message) ||
                                'Gagal mengosongkan pallet.';
                            Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
                            table.ajax.reload(null, false);
                        });
                    });
                }

                function run() {
                    $.ajax({
                        url: FINISH_URL.replace('__id__', plId),
                        type: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                            'Accept': 'application/json'
                        },
                    }).done(function (res) {
                        const pallets = res.pallets_to_clear || [];

                        if (pallets.length) {
                            askToClearPallets(plId, pallets);
                            return;
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Finished',
                                text: res.message || 'Packing list berhasil di-Finish.',
                                timer: 2800,
                                showConfirmButton: false,
                            });
                        } else {
                            alert(res.message || 'Packing list berhasil di-Finish.');
                        }

                        table.ajax.reload(null, false);
                    }).fail(function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ||
                            'Gagal menyimpan status Finished.';

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
                        } else {
                            alert(msg);
                        }
                    });
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'question',
                        title: 'Tandai packing list Finished?',
                        html: html,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Finish',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#1cc88a',
                        focusCancel: true,
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            run();
                        }
                    });
                } else if (confirm('Tandai packing list ini sebagai Finished?')) {
                    run();
                }
            }

            $('#tablePackingLists').on('click', '.btn-finish-pl', function () {
                finishPackingList(
                    $(this).data('pl'),
                    $(this).data('label'),
                    $(this).data('rows'),
                    $(this).data('carton')
                );
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
