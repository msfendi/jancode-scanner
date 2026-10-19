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

                    <!-- 2. Scan / Input Karton Keluar + Log -->
                    <div id="outWorkCard" class="card shadow mb-4 d-none">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-barcode mr-1"></i> 2. Scan / Input Karton Keluar
                            </h6>
                            <span class="badge badge-light border text-gray-700" id="plLabelBadge">-</span>
                        </div>
                        <div class="card-body">

                            <div class="form-row">
                                <div class="col-md-9">
                                    <input type="text" id="scanJan" class="form-control form-control-lg"
                                        placeholder="Scan barcode JAN (1 scan = 1 karton)..." autocomplete="off">
                                </div>
                                <div class="col-md-3 mt-2 mt-md-0">
                                    <button id="btn-add-scan"
                                        class="btn btn-success btn-lg btn-block font-weight-bold">
                                        <i class="fas fa-plus-circle mr-1"></i> Tambah
                                    </button>
                                </div>
                            </div>

                            <button id="btn-open-pick" class="btn btn-outline-primary btn-sm mt-2">
                                <i class="fas fa-list mr-1"></i> Pilih dari Daftar / Input Qty per Jancode
                            </button>

                            <div id="scanInfo" class="mt-3"></div>

                            <hr>

                            <!-- Log langsung di bawah input scan -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-xs font-weight-bold text-uppercase text-muted">
                                    <i class="fas fa-stream mr-1"></i> Log Karton Keluar
                                </span>
                                <span class="badge badge-primary badge-pill" id="plannedTotalBadge">0 karton</span>
                            </div>
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="thead-light sticky-top">
                                        <tr>
                                            <th style="min-width:120px">JAN Code</th>
                                            <th>Color / Size</th>
                                            <th width="80" class="text-center">Qty</th>
                                            <th style="min-width:180px">Pallet Asal</th>
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

                    <!-- 3. Riwayat -->
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
                        <i class="fas fa-list mr-1"></i> Pilih Baris / Input Qty per Jancode
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

            // Total qty yang sudah masuk log (opsional kecualikan 1 entri).
            function logQty(filterFn) {
                return planned.filter(filterFn).reduce((s, p) => s + Number(p.qty_carton || 0), 0);
            }

            // Sisa maksimal yang boleh diambil untuk 1 baris + 1 pallet (mengecualikan entri tertentu).
            function maxFor(row, addr, exceptUid) {
                const usedRow = logQty(p =>
                    String(p.jancode_id) === String(row.id) && p.uid !== exceptUid
                );
                const maxReceive = row.receive - usedRow;

                let maxThis;
                if (addr) {
                    const pl = (row.pallets || []).find(x => String(x.address_id) === String(addr));
                    const plQty = pl ? pl.qty : 0;
                    const usedPl = logQty(p =>
                        String(p.jancode_id) === String(row.id) &&
                        String(p.pallet_address_id) === String(addr) &&
                        p.uid !== exceptUid
                    );
                    maxThis = Math.min(maxReceive, plQty - usedPl);
                } else {
                    const usedUn = logQty(p =>
                        String(p.jancode_id) === String(row.id) &&
                        !p.pallet_address_id &&
                        p.uid !== exceptUid
                    );
                    maxThis = Math.min(maxReceive, row.unplaced - usedUn);
                }

                return Math.max(0, maxThis);
            }

            // Pallet asal default: pallet pertama yang masih punya sisa; jika tidak ada, "belum di pallet".
            function defaultPallet(row) {
                for (const pl of (row.pallets || [])) {
                    if (maxFor(row, pl.address_id, null) > 0) return pl.address_id;
                }
                return null;
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
                        $('#plLabelBadge').text(res.packing_list.group_label || '-');
                        renderPlanned();
                        renderPick('');
                        $('#outWorkCard').removeClass('d-none');
                        $('#scanInfo').html('');
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

            // ── Tambah ke log (merge per jancode + pallet) ─────────────────
            function addToLog(jancodeId, qty, palletAddressId) {
                const row = rowById(jancodeId);
                if (!row) {
                    toast('error', 'Baris tidak ditemukan.');
                    return 0;
                }

                const addr = palletAddressId ? parseInt(palletAddressId, 10) : null;

                // Entri yang sudah ada untuk kombinasi jancode + pallet yang sama.
                let entry = planned.find(p =>
                    String(p.jancode_id) === String(row.id) &&
                    String(p.pallet_address_id || '') === String(addr || '')
                );

                // Batas total entri ini (mengecualikan qty entri itu sendiri),
                // lalu kurangi qty yang sudah ada → sisa yang masih boleh ditambah.
                const ceiling = maxFor(row, addr, entry ? entry.uid : null);
                const allowance = ceiling - (entry ? Number(entry.qty_carton) : 0);
                if (allowance <= 0) {
                    if (addr) {
                        const pl = (row.pallets || []).find(x => String(x.address_id) === String(addr));
                        toast('warning', 'Pallet ' + (pl ? pl.code : addr) + ' sudah habis untuk karton ini.');
                    } else {
                        toast('warning', row.jancode + ': tidak ada karton yang bisa diambil.');
                    }
                    return 0;
                }

                const addQty = Math.min(parseInt(qty, 10) || 1, allowance);

                if (!entry) {
                    entry = {
                        uid: ++uidSeq,
                        jancode_id: row.id,
                        jancode: row.jancode,
                        color: row.color,
                        size: row.size,
                        qty_carton: 0,
                        pallet_address_id: addr,
                    };
                    planned.push(entry);
                }
                entry.qty_carton += addQty;

                if (addQty < (parseInt(qty, 10) || 1)) {
                    toast('warning', 'Qty disesuaikan ke sisa yang tersedia (' + addQty + ' karton).');
                }

                renderPlanned();
                return addQty;
            }

            // ── Scan barcode (1 scan = 1 karton) ───────────────────────────
            $('#scanJan').on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#btn-add-scan').trigger('click');
                }
            });

            $('#btn-add-scan').on('click', function () {
                const code = ($('#scanJan').val() || '').replace(/[^0-9]/g, '');

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
                        const added = addToLog(row.id, 1, defaultPallet(row));

                        if (added > 0) {
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

            // ── Render log ─────────────────────────────────────────────────
            function optionPallet(row, item) {
                let html = '<option value="">Belum di pallet</option>';
                (row.pallets || []).forEach(function (pl) {
                    const isSel = String(item.pallet_address_id) === String(pl.address_id);
                    const avail = maxFor(row, pl.address_id, item.uid);
                    const disabled = (avail <= 0 && !isSel) ? 'disabled' : '';
                    html += '<option value="' + pl.address_id + '" ' + (isSel ? 'selected' : '') + ' ' + disabled + '>' +
                        'Pallet ' + pl.code + ' (maks ' + avail + ')</option>';
                });
                return html;
            }

            function renderPlanned() {
                const $body = $('#plannedBody').empty();
                let total = 0;

                if (!planned.length) {
                    $body.append('<tr><td colspan="5" class="text-center text-muted py-4">Belum ada karton. Scan barcode atau pilih dari daftar.</td></tr>');
                    $('#plannedTotalBadge').text('0 karton');
                    return;
                }

                planned.forEach(function (item) {
                    total += Number(item.qty_carton);
                    const row = rowById(item.jancode_id) || { pallets: [] };
                    const maxThis = Math.max(1, maxFor(row, item.pallet_address_id, item.uid));

                    const tr =
                        '<tr data-uid="' + item.uid + '">' +
                          '<td><span class="font-weight-bold">' + item.jancode + '</span></td>' +
                          '<td><span class="small">' + (item.color || '-') + '</span><br><span class="small text-muted">' + (item.size || '-') + '</span></td>' +
                          '<td class="text-center"><input type="number" class="form-control form-control-sm qty-input text-center" value="' + item.qty_carton + '" min="1" max="' + maxThis + '" step="1"></td>' +
                          '<td><select class="form-control form-control-sm pallet-select">' + optionPallet(row, item) + '</select></td>' +
                          '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="fas fa-times"></i></button></td>' +
                        '</tr>';

                    $body.append(tr);
                });

                $('#plannedTotalBadge').text(total + ' karton');
            }

            // edit qty di log
            $(document).on('change', '.qty-input', function () {
                const uid = parseInt($(this).closest('tr').data('uid'), 10);
                const item = planned.find(p => p.uid === uid);
                if (!item) return;

                let val = parseInt($(this).val(), 10) || 1;
                let max = maxFor(rowById(item.jancode_id), item.pallet_address_id, item.uid);
                if (max < 1) max = 1;
                if (val > max) { val = max; toast('warning', 'Qty melebihi sisa yang tersedia (maks ' + max + ').'); }
                if (val < 1) { val = 1; }
                item.qty_carton = val;

                renderPlanned();
            });

            // Gabungkan entri yang punya kombinasi jancode + pallet sama.
            function mergePlanned() {
                const seen = {};
                const result = [];
                planned.forEach(function (p) {
                    const key = p.jancode_id + '|' + (p.pallet_address_id || '');
                    if (seen[key]) {
                        seen[key].qty_carton += Number(p.qty_carton);
                    } else {
                        seen[key] = p;
                        result.push(p);
                    }
                });
                planned = result;
            }

            // ganti pallet asal
            $(document).on('change', '.pallet-select', function () {
                const uid = parseInt($(this).closest('tr').data('uid'), 10);
                const item = planned.find(p => p.uid === uid);
                if (!item) return;

                const prev = item.pallet_address_id;
                const val = $(this).val();
                const newAddr = val ? parseInt(val, 10) : null;

                // Cek kapasitas tujuan (pallet asal baru / belum di pallet).
                const row = rowById(item.jancode_id) || { pallets: [] };
                const usedDest = logQty(p =>
                    p.uid !== uid &&
                    String(p.jancode_id) === String(item.jancode_id) &&
                    String(p.pallet_address_id || '') === String(newAddr || '')
                );
                let capDest = row.unplaced;
                if (newAddr) {
                    const pl = (row.pallets || []).find(x => String(x.address_id) === String(newAddr));
                    capDest = pl ? pl.qty : 0;
                }

                if (usedDest + Number(item.qty_carton) > capDest) {
                    // Kembalikan pilihan sebelumnya bila tujuan tidak cukup.
                    item.pallet_address_id = prev;
                    renderPlanned();
                    toast('warning', newAddr
                        ? 'Pallet tujuan tidak cukup untuk ' + item.qty_carton + ' karton ini.'
                        : 'Karton yang belum ditempatkan tidak cukup untuk ' + item.qty_carton + ' karton ini.');
                    return;
                }

                item.pallet_address_id = newAddr;
                mergePlanned();
                renderPlanned();
            });

            // hapus entri di log
            $(document).on('click', '.btn-remove', function () {
                const uid = parseInt($(this).closest('tr').data('uid'), 10);
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

                    const used = logQty(p => String(p.jancode_id) === String(row.id));
                    const sisa = row.receive - used;

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

                if (addToLog(id, qty, defaultPallet(row)) > 0) {
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
                    toast('warning', 'Belum ada karton pada log.');
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
                        renderPlanned();
                        renderPick('');
                        $('#outNotes').val('');
                        $('#scanInfo').html('');
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
