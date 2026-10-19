<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@x.x.x/dist/select2-bootstrap4.min.css"
    rel="stylesheet" />

<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">

            <div id="content">
                @include('layout.navbar')
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h4 mb-0 text-gray-800">
                                <i class="fas fa-box-open text-primary mr-1"></i> Karton Keluar (Scan Out)
                            </h1>
                            <p class="mb-0 text-muted small">
                                Ambil karton yang sudah discan / sudah di pallet dari sebuah packing list.
                                Receive otomatis berkurang dan data pallet ikut berubah.
                            </p>
                        </div>
                        <div>
                            <a href="{{ route('scanner.index') }}" class="btn btn-sm btn-outline-primary shadow-sm mr-2">
                                <i class="fas fa-barcode fa-sm mr-1"></i> Scanner Karton
                            </a>
                            <a href="{{ route('pallet.index') }}" class="btn btn-sm btn-info shadow-sm">
                                <i class="fas fa-boxes fa-sm mr-1"></i> Manage Pallet
                            </a>
                        </div>
                    </div>

                    @include('layout.workflow-steps', ['current' => 'scan'])
                    @include('layout.session-banner', ['currentSession' => $currentSession])

                    @php $canOut = $currentSession && $currentSession->isOpen(); @endphp

                    @if (!$canOut)
                        <div class="card shadow mb-4 border-left-warning">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-lock fa-2x text-warning mb-3"></i>
                                <h5 class="font-weight-bold text-gray-800">Karton keluar terkunci</h5>
                                <p class="text-muted mb-3">
                                    @if (!$currentSession)
                                        Mulai session terlebih dahulu sebelum mengambil karton.
                                    @else
                                        Session <strong>{{ $currentSession->code }}</strong> sudah ditutup.
                                        Pilih session lain atau mulai session baru.
                                    @endif
                                </p>
                                <a href="{{ route('session.index') }}" class="btn btn-primary font-weight-bold">
                                    <i class="fas fa-play-circle mr-1"></i> Ke Halaman Session
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- 1. Pilih Packing List -->
                    <div class="card shadow mb-4 {{ $canOut ? '' : 'd-none' }}">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-clipboard-list mr-1"></i> 1. Pilih Packing List (Revisi Aktif)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-end">
                                <div class="col-md-8">
                                    <label class="small font-weight-bold text-gray-700">Packing List</label>
                                    <select id="packingListSelect" class="form-control" style="width:100%"></select>
                                </div>
                                <div class="col-md-4 mt-2 mt-md-0">
                                    <button id="btn-load" class="btn btn-primary font-weight-bold btn-block">
                                        <i class="fas fa-download mr-1"></i> Muat Packing List
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Hanya revisi <strong>aktif</strong> pada session aktif yang bisa diambil kartonya.
                            </small>
                        </div>
                    </div>

                    <!-- 2 & 3. Input & Rencana -->
                    <div id="outWorkCard" class="d-none">
                        <div class="row">
                            <div class="col-lg-5">

                                <!-- Scan / tambah -->
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-barcode mr-1"></i> 2. Scan / Tambah Karton
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-row">
                                            <div class="col-8">
                                                <label class="small font-weight-bold text-gray-700">Barcode JAN</label>
                                                <input type="text" id="scanJan" class="form-control form-control-lg"
                                                    placeholder="Scan barcode..." autocomplete="off">
                                            </div>
                                            <div class="col-4">
                                                <label class="small font-weight-bold text-gray-700">Qty</label>
                                                <input type="number" id="scanQty" class="form-control form-control-lg"
                                                    value="1" min="1" step="1">
                                            </div>
                                        </div>
                                        <button id="btn-add-scan" class="btn btn-success btn-block font-weight-bold mt-3">
                                            <i class="fas fa-plus-circle mr-1"></i> Tambahkan
                                        </button>
                                        <button id="btn-open-pick" class="btn btn-outline-primary btn-block mt-2">
                                            <i class="fas fa-list mr-1"></i> Pilih dari Daftar
                                        </button>

                                        <div id="scanInfo" class="mt-3"></div>
                                    </div>
                                </div>

                                <!-- Info packing list -->
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-info-circle mr-1"></i> Info Packing List
                                        </h6>
                                    </div>
                                    <div class="card-body" id="plInfo">
                                        <p class="text-muted mb-0">Belum ada packing list dimuat.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-7">
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-clipboard-check mr-1"></i> 3. Rencana Karton Keluar
                                        </h6>
                                        <span class="badge badge-primary badge-pill" id="plannedTotalBadge">0 karton</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="thead-light sticky-top">
                                                    <tr>
                                                        <th style="min-width:120px">JAN Code</th>
                                                        <th>Color / Size</th>
                                                        <th width="70" class="text-center">Qty</th>
                                                        <th style="min-width:170px">Pallet Asal</th>
                                                        <th width="40" class="text-center"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="plannedBody"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-white">
                                        <label class="small font-weight-bold text-gray-700">Catatan (opsional)</label>
                                        <input type="text" id="outNotes" class="form-control mb-3"
                                            placeholder="mis. permintaan packing / revisi proforma baru">

                                        <button id="btn-save" class="btn btn-primary btn-lg btn-block font-weight-bold">
                                            <i class="fas fa-save mr-1"></i> Simpan Karton Keluar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Riwayat -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-history mr-1"></i> Riwayat Karton Keluar
                                <span class="text-muted small font-weight-normal">(session aktif)</span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="tableCartonOut" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Kode Batch</th>
                                            <th>Waktu</th>
                                            <th>Packing List</th>
                                            <th>JAN Code</th>
                                            <th>Color</th>
                                            <th>Size</th>
                                            <th class="text-right">Qty</th>
                                            <th>Pallet Asal</th>
                                            <th>Operator</th>
                                            <th>Catatan</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            @include('layout.footer')

        </div>
    </div>

    <!-- Modal: Pilih dari Daftar -->
    <div class="modal fade" id="pickModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-list mr-1"></i> Pilih Baris dari Packing List
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="text" id="pickSearch" class="form-control mb-3" placeholder="Cari JAN / color / size...">
                    <div class="table-responsive" style="max-height: 55vh; overflow-y: auto;">
                        <table class="table table-sm table-hover table-bordered mb-0">
                            <thead class="thead-light sticky-top">
                                <tr>
                                    <th>JAN Code</th>
                                    <th>Color</th>
                                    <th>Size</th>
                                    <th class="text-right">Receive</th>
                                    <th class="text-right">Sisa</th>
                                    <th class="text-right">Di Pallet</th>
                                    <th width="90" class="text-center">Qty</th>
                                    <th width="90" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="pickBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script type="text/javascript">
        $(document).ready(function () {

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            const URLS = {
                packingLists: "{{ route('cartonOut.packingLists') }}",
                load:         "{{ route('cartonOut.load') }}",
                rows:         "{{ route('cartonOut.rows') }}",
                lookup:       "{{ route('cartonOut.lookup') }}",
                store:        "{{ route('cartonOut.store') }}",
                history:      "{{ route('cartonOut.history') }}",
            };

            let currentBatchId = null;
            let rows = [];
            let planned = [];
            let uidSeq = 0;

            const $select = $('#packingListSelect');

            // ── Select2 packing list ───────────────────────────────────────
            $select.select2({
                theme: 'bootstrap4',
                placeholder: 'Cari packing list (style / CPO / destination / warehouse)...',
                allowClear: true,
                ajax: {
                    url: URLS.packingLists,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    processResults: data => ({ results: data }),
                    cache: true
                }
            });

            // ── DataTable riwayat ──────────────────────────────────────────
            const historyTable = $('#tableCartonOut').DataTable({
                processing: true,
                serverSide: false,
                order: [],
                pageLength: 25,
                ajax: {
                    url: URLS.history,
                    dataSrc: 'data'
                },
                columns: [
                    { data: 'code' },
                    { data: 'created_at' },
                    { data: 'packing_list' },
                    { data: 'jancode' },
                    { data: 'color' },
                    { data: 'size' },
                    { data: 'qty_carton', className: 'text-right' },
                    { data: 'pallet_code' },
                    { data: 'user_name' },
                    { data: 'notes', defaultContent: '-' },
                ],
                language: { emptyTable: 'Belum ada riwayat karton keluar pada session ini.' }
            });

            function reloadHistory() {
                historyTable.ajax.reload(null, false);
            }

            // ── Helpers ────────────────────────────────────────────────────
            function rowById(id) {
                return rows.find(r => String(r.id) === String(id)) || null;
            }
            function rowByJan(code) {
                return rows.find(r => String(r.jancode) === String(code)) || null;
            }
            function plannedQty(filterFn) {
                return planned.filter(filterFn).reduce((s, p) => s + Number(p.qty_carton || 0), 0);
            }
            function toast(icon, title) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: icon,
                    title: title,
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true
                });
            }

            // ── Muat packing list ──────────────────────────────────────────
            $('#btn-load').on('click', function () {
                const id = $select.val();
                if (!id) {
                    toast('warning', 'Pilih packing list terlebih dahulu.');
                    return;
                }

                const $btn = $(this).prop('disabled', true);

                $.post(URLS.load, { import_batch_id: id })
                    .done(function (res) {
                        if (!res.success) {
                            Swal.fire('Gagal', res.message || 'Tidak bisa memuat packing list.', 'error');
                            return;
                        }
                        currentBatchId = res.packing_list.id;
                        rows = res.rows || [];
                        planned = [];
                        renderInfo(res);
                        renderPlanned();
                        renderPick();
                        $('#outWorkCard').removeClass('d-none');
                        toast('success', 'Packing list dimuat.');
                        $('#scanJan').trigger('focus');
                    })
                    .fail(function (xhr) {
                        Swal.fire('Gagal', (xhr.responseJSON && xhr.responseJSON.message) || 'Tidak bisa memuat packing list.', 'error');
                    })
                    .always(function () {
                        $btn.prop('disabled', false);
                    });
            });

            // ── Render info packing list ───────────────────────────────────
            function renderInfo(res) {
                const pl = res.packing_list || {};
                const t = res.totals || {};
                const html =
                    '<div class="mb-1"><strong>' + (pl.group_label || '-') + '</strong></div>' +
                    '<div class="small text-muted mb-2">' + (pl.doc_type || '') + ' Rev.' + (pl.revision_no || 1) + '</div>' +
                    '<div class="row text-center">' +
                      '<div class="col"><div class="small text-muted">Receive</div><div class="h5 mb-0 font-weight-bold text-success">' + (t.receive || 0) + '</div></div>' +
                      '<div class="col"><div class="small text-muted">Sudah Keluar</div><div class="h5 mb-0 font-weight-bold text-danger">' + (t.out || 0) + '</div></div>' +
                      '<div class="col"><div class="small text-muted">Di Pallet</div><div class="h5 mb-0 font-weight-bold text-primary">' + (t.placed || 0) + '</div></div>' +
                    '</div>';
                $('#plInfo').html(html);
            }

            // ── Tambah item ────────────────────────────────────────────────
            function addPlanned(jancodeId, qty, palletAddressId) {
                const row = rowById(jancodeId);
                if (!row) {
                    toast('error', 'Baris tidak ditemukan.');
                    return false;
                }

                qty = parseInt(qty, 10) || 1;
                const usedReceive = plannedQty(p => String(p.jancode_id) === String(row.id));
                const remainReceive = row.receive - usedReceive;

                if (remainReceive <= 0) {
                    toast('warning', row.jancode + ': tidak ada karton yang bisa diambil.');
                    return false;
                }
                if (qty > remainReceive) { qty = remainReceive; }

                const addr = palletAddressId ? String(palletAddressId) : null;

                if (addr) {
                    const pl = (row.pallets || []).find(x => String(x.address_id) === addr);
                    const plQty = pl ? pl.qty : 0;
                    const usedPl = plannedQty(p => String(p.jancode_id) === String(row.id) && String(p.pallet_address_id) === addr);
                    const remainPl = plQty - usedPl;
                    if (remainPl <= 0) {
                        toast('warning', 'Pallet ' + (pl ? pl.code : addr) + ' sudah habis untuk karton ini.');
                        return false;
                    }
                    if (qty > remainPl) { qty = remainPl; }
                } else {
                    const usedUn = plannedQty(p => String(p.jancode_id) === String(row.id) && !p.pallet_address_id);
                    const remainUn = row.unplaced - usedUn;
                    if (remainUn <= 0) {
                        toast('warning', 'Tidak ada karton ' + row.jancode + ' yang belum ditempatkan.');
                        return false;
                    }
                    if (qty > remainUn) { qty = remainUn; }
                }

                planned.push({
                    uid: ++uidSeq,
                    jancode_id: row.id,
                    jancode: row.jancode,
                    color: row.color,
                    size: row.size,
                    qty_carton: qty,
                    pallet_address_id: addr ? parseInt(addr, 10) : null,
                });

                renderPlanned();
                return true;
            }

            // ── Scan barcode ───────────────────────────────────────────────
            $('#scanJan').on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#btn-add-scan').trigger('click');
                }
            });

            $('#btn-add-scan').on('click', function () {
                const code = ($('#scanJan').val() || '').replace(/[^0-9]/g, '');
                const qty = parseInt($('#scanQty').val(), 10) || 1;

                if (code === '') {
                    toast('warning', 'Scan / masukkan barcode terlebih dahulu.');
                    return;
                }
                if (!currentBatchId) {
                    toast('warning', 'Muat packing list terlebih dahulu.');
                    return;
                }

                const $btn = $(this).prop('disabled', true);

                $.get(URLS.lookup, { jancode: code })
                    .done(function (res) {
                        if (!res.success) {
                            $('#scanInfo').html('<div class="alert alert-warning py-2 mb-0">' + res.message + '</div>');
                            return;
                        }
                        const row = res.row;
                        const defPallet = (row.pallets && row.pallets.length) ? row.pallets[0].address_id : null;

                        if (addPlanned(row.id, qty, defPallet)) {
                            $('#scanInfo').html(
                                '<div class="alert alert-success py-2 mb-0">' +
                                '<strong>' + row.jancode + '</strong> — ' + (row.color || '-') + ' / ' + (row.size || '-') +
                                '<br><span class="small">Receive: ' + row.receive + ' · Di pallet: ' + row.placed + ' · Belum di pallet: ' + row.unplaced + '</span>' +
                                '</div>'
                            );
                        }
                        $('#scanJan').val('').trigger('focus');
                    })
                    .fail(function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Barcode tidak ditemukan.';
                        $('#scanInfo').html('<div class="alert alert-danger py-2 mb-0">' + msg + '</div>');
                        $('#scanJan').val('').trigger('focus');
                    })
                    .always(function () {
                        $btn.prop('disabled', false);
                    });
            });

            // ── Render rencana ─────────────────────────────────────────────
            function optionPallet(row, item, selectedAddr) {
                let html = '<option value="">— Belum di pallet —</option>';
                (row.pallets || []).forEach(function (pl) {
                    const usedOthers = plannedQty(p =>
                        p.uid !== item.uid &&
                        String(p.jancode_id) === String(row.id) &&
                        String(p.pallet_address_id) === String(pl.address_id)
                    );
                    const avail = pl.qty - usedOthers;
                    const isSel = String(selectedAddr) === String(pl.address_id);
                    const disabled = (avail <= 0 && !isSel) ? 'disabled' : '';
                    html += '<option value="' + pl.address_id + '" ' + (isSel ? 'selected' : '') + ' ' + disabled + '>' +
                        'Pallet ' + pl.code + ' (tersedia ' + Math.max(0, avail) + ')</option>';
                });
                return html;
            }

            function renderPlanned() {
                const $body = $('#plannedBody').empty();
                let total = 0;

                if (!planned.length) {
                    $body.append('<tr><td colspan="5" class="text-center text-muted py-4">Belum ada karton dalam rencana.</td></tr>');
                    $('#plannedTotalBadge').text('0 karton');
                    return;
                }

                planned.forEach(function (item) {
                    total += Number(item.qty_carton);
                    const row = rowById(item.jancode_id) || { pallets: [] };

                    const usedReceive = plannedQty(p => p.uid !== item.uid && String(p.jancode_id) === String(item.jancode_id));
                    const maxReceive = row.receive - usedReceive;

                    let maxThis;
                    if (item.pallet_address_id) {
                        const pl = (row.pallets || []).find(x => String(x.address_id) === String(item.pallet_address_id));
                        const plQty = pl ? pl.qty : 0;
                        const usedPl = plannedQty(p => p.uid !== item.uid && String(p.jancode_id) === String(item.jancode_id) && String(p.pallet_address_id) === String(item.pallet_address_id));
                        maxThis = Math.min(maxReceive, plQty - usedPl);
                    } else {
                        const usedUn = plannedQty(p => p.uid !== item.uid && String(p.jancode_id) === String(item.jancode_id) && !p.pallet_address_id);
                        maxThis = Math.min(maxReceive, row.unplaced - usedUn);
                    }
                    maxThis = Math.max(1, maxThis);

                    const tr =
                        '<tr data-uid="' + item.uid + '">' +
                          '<td><span class="font-weight-bold">' + item.jancode + '</span></td>' +
                          '<td><span class="small">' + (item.color || '-') + '</span><br><span class="small text-muted">' + (item.size || '-') + '</span></td>' +
                          '<td class="text-center"><input type="number" class="form-control form-control-sm qty-input text-center" value="' + item.qty_carton + '" min="1" max="' + maxThis + '" step="1"></td>' +
                          '<td><select class="form-control form-control-sm pallet-select">' + optionPallet(row, item, item.pallet_address_id) + '</select></td>' +
                          '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="fas fa-times"></i></button></td>' +
                        '</tr>';

                    $body.append(tr);
                });

                $('#plannedTotalBadge').text(total + ' karton');
            }

            // edit qty
            $(document).on('change', '.qty-input', function () {
                const uid = $(this).closest('tr').data('uid');
                const item = planned.find(p => p.uid === uid);
                if (!item) return;

                let val = parseInt($(this).val(), 10) || 1;
                const max = parseInt($(this).attr('max'), 10) || 1;
                if (val > max) { val = max; toast('warning', 'Qty melebihi sisa yang tersedia (maks ' + max + ').'); }
                if (val < 1) { val = 1; }
                item.qty_carton = val;

                renderPlanned();
            });

            // ganti pallet
            $(document).on('change', '.pallet-select', function () {
                const $tr = $(this).closest('tr');
                const uid = $tr.data('uid');
                const item = planned.find(p => p.uid === uid);
                if (!item) return;

                const val = $(this).val();
                item.pallet_address_id = val ? parseInt(val, 10) : null;

                // clamp qty ke sisa yang tersedia
                const row = rowById(item.jancode_id) || { pallets: [] };
                const usedReceive = plannedQty(p => p.uid !== item.uid && String(p.jancode_id) === String(item.jancode_id));
                const maxReceive = row.receive - usedReceive;

                let maxThis;
                if (item.pallet_address_id) {
                    const pl = (row.pallets || []).find(x => String(x.address_id) === String(item.pallet_address_id));
                    const plQty = pl ? pl.qty : 0;
                    const usedPl = plannedQty(p => p.uid !== item.uid && String(p.jancode_id) === String(item.jancode_id) && String(p.pallet_address_id) === String(item.pallet_address_id));
                    maxThis = Math.min(maxReceive, plQty - usedPl);
                } else {
                    const usedUn = plannedQty(p => p.uid !== item.uid && String(p.jancode_id) === String(item.jancode_id) && !p.pallet_address_id);
                    maxThis = Math.min(maxReceive, row.unplaced - usedUn);
                }
                if (maxThis < 1) { maxThis = 1; }
                if (item.qty_carton > maxThis) { item.qty_carton = maxThis; }

                renderPlanned();
            });

            // hapus item
            $(document).on('click', '.btn-remove', function () {
                const uid = $(this).closest('tr').data('uid');
                planned = planned.filter(p => p.uid !== uid);
                renderPlanned();
            });

            // ── Modal pilih dari daftar ────────────────────────────────────
            function renderPick(filter) {
                filter = (filter || '').toLowerCase();
                const $body = $('#pickBody').empty();

                rows.forEach(function (row) {
                    if (filter &&
                        !String(row.jancode).toLowerCase().includes(filter) &&
                        !String(row.color || '').toLowerCase().includes(filter) &&
                        !String(row.size || '').toLowerCase().includes(filter)) {
                        return;
                    }

                    const usedReceive = plannedQty(p => String(p.jancode_id) === String(row.id));
                    const sisa = row.receive - usedReceive;

                    const tr =
                        '<tr data-id="' + row.id + '">' +
                          '<td class="font-weight-bold">' + row.jancode + '</td>' +
                          '<td>' + (row.color || '-') + '</td>' +
                          '<td>' + (row.size || '-') + '</td>' +
                          '<td class="text-right">' + row.receive + '</td>' +
                          '<td class="text-right ' + (sisa > 0 ? 'text-success font-weight-bold' : 'text-muted') + '">' + Math.max(0, sisa) + '</td>' +
                          '<td class="text-right">' + row.placed + '</td>' +
                          '<td class="text-center"><input type="number" class="form-control form-control-sm pick-qty text-center" value="1" min="1" step="1" ' + (sisa <= 0 ? 'disabled' : '') + '></td>' +
                          '<td class="text-center"><button type="button" class="btn btn-sm btn-primary btn-pick-add" ' + (sisa <= 0 ? 'disabled' : '') + '><i class="fas fa-plus"></i></button></td>' +
                        '</tr>';
                    $body.append(tr);
                });

                if (!$body.children().length) {
                    $body.append('<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada baris.</td></tr>');
                }
            }

            $('#btn-open-pick').on('click', function () {
                if (!currentBatchId) {
                    toast('warning', 'Muat packing list terlebih dahulu.');
                    return;
                }
                $('#pickSearch').val('');
                renderPick('');
                $('#pickModal').modal('show');
            });

            $('#pickSearch').on('input', function () {
                renderPick($(this).val());
            });

            $(document).on('click', '.btn-pick-add', function () {
                const $tr = $(this).closest('tr');
                const id = $tr.data('id');
                const qty = parseInt($tr.find('.pick-qty').val(), 10) || 1;
                const row = rowById(id);
                const defPallet = (row && row.pallets && row.pallets.length) ? row.pallets[0].address_id : null;

                if (addPlanned(id, qty, defPallet)) {
                    renderPick($('#pickSearch').val());
                }
            });

            // ── Simpan ─────────────────────────────────────────────────────
            $('#btn-save').on('click', function () {
                if (!currentBatchId) {
                    toast('warning', 'Muat packing list terlebih dahulu.');
                    return;
                }
                if (!planned.length) {
                    toast('warning', 'Belum ada karton dalam rencana.');
                    return;
                }

                const total = planned.reduce((s, p) => s + Number(p.qty_carton), 0);

                Swal.fire({
                    title: 'Simpan Karton Keluar?',
                    html: 'Total <strong>' + total + ' karton</strong> akan dikeluarkan dari packing list ini.<br>' +
                          '<span class="text-muted small">Receive akan berkurang dan data pallet ikut berubah.</span>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Simpan',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#4e73df'
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    const $btn = $('#btn-save').prop('disabled', true);

                    $.post(URLS.store, {
                        import_batch_id: currentBatchId,
                        notes: $('#outNotes').val(),
                        items: planned.map(p => ({
                            jancode_id: p.jancode_id,
                            qty_carton: p.qty_carton,
                            pallet_address_id: p.pallet_address_id
                        }))
                    })
                    .done(function (res) {
                        if (!res.success) {
                            Swal.fire('Gagal', res.message || 'Gagal menyimpan.', 'error');
                            return;
                        }
                        rows = res.rows || [];
                        planned = [];
                        renderInfo(res);
                        renderPlanned();
                        renderPick('');
                        $('#outNotes').val('');
                        reloadHistory();
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            html: res.message + '<br><span class="small text-muted">Kode batch: <strong>' + res.code + '</strong></span>'
                        });
                    })
                    .fail(function (xhr) {
                        Swal.fire('Gagal', (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menyimpan karton keluar.', 'error');
                    })
                    .always(function () {
                        $btn.prop('disabled', false);
                    });
                });
            });

        });
    </script>
</body>

</html>
