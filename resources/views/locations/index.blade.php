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

                    <div class="d-sm-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h1 class="h4 mb-0 text-gray-800">
                                <i class="fas fa-map-marker-alt text-primary mr-1"></i> Manage Lokasi
                            </h1>
                            <p class="mb-0 text-muted small">
                                Slot tetap gudang. Kode lokasi = isi QR yang dicetak pada label.
                            </p>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge badge-light border mr-2 py-2 px-3">
                                Total <strong class="text-primary">{{ $locations->count() }}</strong> lokasi
                            </span>
                            <span class="badge badge-light border py-2 px-3">
                                Aktif <strong class="text-success" id="statAktif">-</strong>
                            </span>
                        </div>
                    </div>

                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    @endif

                    <!-- Filter & aksi massal -->
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-body py-3">
                            <div class="row align-items-end">
                                <div class="col-md-2 mb-2 mb-md-0">
                                    <label class="small font-weight-bold text-gray-600 mb-1">Row</label>
                                    <select id="filterRow" class="form-control form-control-sm">
                                        <option value="">Semua row</option>
                                        @foreach ($rows as $row)
                                            <option value="{{ $row }}">{{ $row }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2 mb-md-0">
                                    <label class="small font-weight-bold text-gray-600 mb-1">Kolom</label>
                                    <select id="filterCol" class="form-control form-control-sm">
                                        <option value="">Semua kolom</option>
                                        @foreach ($columns as $col)
                                            <option value="{{ $col }}">{{ $col }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2 mb-md-0">
                                    <label class="small font-weight-bold text-gray-600 mb-1">Status</label>
                                    <select id="filterStatus" class="form-control form-control-sm">
                                        <option value="">Semua status</option>
                                        <option value="aktif">Aktif</option>
                                        <option value="nonaktif">Nonaktif</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2 mb-md-0">
                                    <label class="small font-weight-bold text-gray-600 mb-1">Isi</label>
                                    <select id="filterIsi" class="form-control form-control-sm">
                                        <option value="">Semua</option>
                                        <option value="terisi">Terisi</option>
                                        <option value="kosong">Kosong</option>
                                    </select>
                                </div>
                                <div class="col-md-4 text-md-right">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnPrintSelected">
                                        <i class="fas fa-print mr-1"></i> Cetak label terpilih
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnBulkActive">
                                        <i class="fas fa-check mr-1"></i> Aktifkan
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnBulkInactive">
                                        <i class="fas fa-ban mr-1"></i> Nonaktifkan
                                    </button>
                                </div>
                            </div>

                            <hr class="my-3">

                            <!-- Cetak per range -->
                            <form method="GET" action="{{ route('locations.labels') }}" target="_blank" class="form-inline">
                                <span class="small font-weight-bold text-gray-600 mr-2">Cetak range:</span>
                                <input type="text" name="from" class="form-control form-control-sm mr-2 mb-1"
                                    placeholder="dari, mis. B-a-01" value="{{ request('from') }}">
                                <input type="text" name="to" class="form-control form-control-sm mr-2 mb-1"
                                    placeholder="sampai, mis. B-f-19" value="{{ request('to') }}">
                                <select name="row" class="form-control form-control-sm mr-2 mb-1">
                                    <option value="">Semua row</option>
                                    @foreach ($rows as $row)
                                        <option value="{{ $row }}">{{ $row }}</option>
                                    @endforeach
                                </select>
                                <div class="custom-control custom-checkbox mr-2 mb-1">
                                    <input type="checkbox" class="custom-control-input" id="includeInactive" name="include_inactive" value="1">
                                    <label class="custom-control-label small" for="includeInactive">Sertakan nonaktif</label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary mb-1">
                                    <i class="fas fa-print mr-1"></i> Cetak
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Tabel -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-hover" id="tableLocations" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width:32px"><input type="checkbox" id="checkAll"></th>
                                            <th>Kode</th>
                                            <th>Row</th>
                                            <th>Kol</th>
                                            <th class="text-right">Pos</th>
                                            <th class="text-right">Kapasitas</th>
                                            <th>Mode</th>
                                            <th>Status</th>
                                            <th>Pallet terisi</th>
                                            <th style="width:150px" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($locations as $loc)
                                            <tr>
                                                <td><input type="checkbox" class="row-check" value="{{ $loc->id }}"></td>
                                                <td><code class="font-weight-bold">{{ $loc->code }}</code></td>
                                                <td>{{ $loc->row_label }}</td>
                                                <td>{{ $loc->col_label }}</td>
                                                <td class="text-right">{{ $loc->position_no }}</td>
                                                <td class="text-right">{{ $loc->max_carton }}</td>
                                                <td>
                                                    @if ($loc->country_mode === 'multi')
                                                        <span class="badge badge-info">multi</span>
                                                    @else
                                                        <span class="badge badge-light border">single</span>
                                                    @endif
                                                </td>
                                                <td data-status="{{ $loc->is_active ? 'aktif' : 'nonaktif' }}">
                                                    @if ($loc->is_active)
                                                        <span class="badge badge-success">Aktif</span>
                                                    @else
                                                        <span class="badge badge-secondary">Nonaktif</span>
                                                    @endif
                                                </td>
                                                <td data-isi="{{ $loc->total_carton ? 'terisi' : 'kosong' }}">
                                                    @if ($loc->total_carton)
                                                        <span class="small">
                                                            {{ $loc->style ?: '-' }} / {{ $loc->ocf ?: '-' }}
                                                            <span class="text-muted">({{ $loc->total_carton }} karton)</span>
                                                        </span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center text-nowrap">
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-dark btn-qr"
                                                        title="Lihat QR"
                                                        data-code="{{ $loc->code }}"
                                                        data-qr="{{ route('locations.qr', $loc->id) }}">
                                                        <i class="fas fa-qrcode"></i>
                                                    </button>
                                                    <a href="{{ route('locations.show', $loc->id) }}" class="btn btn-sm btn-outline-primary" title="Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    @can('location.manage')
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-secondary btn-edit"
                                                            title="Edit"
                                                            data-id="{{ $loc->id }}"
                                                            data-code="{{ $loc->code }}"
                                                            data-max="{{ $loc->max_carton }}"
                                                            data-mode="{{ $loc->country_mode }}"
                                                            data-active="{{ $loc->is_active ? 1 : 0 }}">
                                                            <i class="fas fa-pen"></i>
                                                        </button>
                                                    @endcan
                                                    <a href="{{ route('locations.labels', ['ids' => [$loc->id]]) }}" target="_blank"
                                                        class="btn btn-sm btn-outline-success" title="Cetak label">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
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

    <!-- Modal QR -->
    <div class="modal fade" id="qrModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title font-weight-bold">QR Lokasi <span id="qrCodeLabel"></span></h6>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body text-center">
                    <img id="qrImage" src="" alt="QR" style="width:260px;height:260px">
                    <p class="small text-muted mb-0">Isi QR: <code id="qrPayload"></code></p>
                </div>
                <div class="modal-footer py-2">
                    <a id="qrDownload" href="#" class="btn btn-sm btn-primary" download>
                        <i class="fas fa-download mr-1"></i> Unduh SVG
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    @can('location.manage')
    <!-- Modal Edit -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form method="POST" id="editForm">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title font-weight-bold">Edit Lokasi <span id="editCodeLabel"></span></h6>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="small font-weight-bold">Kapasitas karton</label>
                            <input type="number" name="max_carton" id="editMax" class="form-control form-control-sm" min="1" max="999" required>
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Mode negara</label>
                            <select name="country_mode" id="editMode" class="form-control form-control-sm">
                                <option value="single">single — satu negara per pallet</option>
                                <option value="multi">multi — boleh campur negara</option>
                            </select>
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="editActive" name="is_active" value="1">
                            <label class="custom-control-label" for="editActive">Lokasi aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Batal</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endcan

    <!-- Form aksi massal -->
    <form method="POST" action="{{ route('locations.bulkToggle') }}" id="bulkForm" class="d-none">
        @csrf
        <input type="hidden" name="active" id="bulkActive" value="1">
        <div id="bulkIds"></div>
    </form>

    <script>
        $(function () {
            var table = $('#tableLocations').DataTable({
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Semua']],
                order: [[1, 'asc']],
                columnDefs: [{ orderable: false, targets: [0, 9] }],
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampil _MENU_ baris',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ lokasi',
                    infoEmpty: 'Tidak ada data',
                    zeroRecords: 'Tidak ada lokasi yang cocok',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
                }
            });

            // Filter kustom -> search gabungan kolom.
            $.fn.dataTable.ext.search.push(function (settings, data) {
                var row = $('#filterRow').val();
                var col = $('#filterCol').val();
                var status = $('#filterStatus').val();
                var isi = $('#filterIsi').val();

                if (row && data[2].trim() !== row) return false;
                if (col && data[3].trim() !== col) return false;
                if (status) {
                    var st = (data[7].toLowerCase().indexOf('nonaktif') !== -1) ? 'nonaktif' : 'aktif';
                    if (st !== status) return false;
                }
                if (isi) {
                    var terisi = data[8].trim() !== '-';
                    if ((isi === 'terisi') !== terisi) return false;
                }

                return true;
            });

            $('#filterRow, #filterCol, #filterStatus, #filterIsi').on('change', function () {
                table.draw();
            });

            function refreshStats() {
                var total = table.rows({ search: 'applied' }).count();
                var aktif = table.rows({ search: 'applied' }).nodes().to$()
                    .find('td[data-status="aktif"]').length;
                $('#statAktif').text(aktif);
            }

            table.on('draw', refreshStats);
            refreshStats();

            // Pilih semua
            $('#checkAll').on('change', function () {
                $('.row-check').prop('checked', $(this).prop('checked'));
            });

            function selectedIds() {
                return $('.row-check:checked').map(function () { return this.value; }).get();
            }

            // QR
            $(document).on('click', '.btn-qr', function () {
                var btn = $(this);
                $('#qrCodeLabel').text(btn.data('code'));
                $('#qrPayload').text(btn.data('code'));
                $('#qrImage').attr('src', btn.data('qr'));
                $('#qrDownload').attr('href', btn.data('qr'));
                $('#qrModal').modal('show');
            });

            // Edit
            @can('location.manage')
            $(document).on('click', '.btn-edit', function () {
                var btn = $(this);
                $('#editCodeLabel').text(btn.data('code'));
                $('#editMax').val(btn.data('max'));
                $('#editMode').val(btn.data('mode'));
                $('#editActive').prop('checked', String(btn.data('active')) === '1');
                $('#editForm').attr('action', '{{ url('locations') }}/' + btn.data('id'));
                $('#editModal').modal('show');
            });
            @endcan

            // Cetak label terpilih
            $('#btnPrintSelected').on('click', function () {
                var ids = selectedIds();
                if (!ids.length) {
                    alert('Pilih minimal satu lokasi.');
                    return;
                }
                var url = '{{ route('locations.labels') }}?' + ids.map(function (id) {
                    return 'ids[]=' + encodeURIComponent(id);
                }).join('&');
                window.open(url, '_blank');
            });

            // Aktif / nonaktif massal
            function bulkToggle(active) {
                var ids = selectedIds();
                if (!ids.length) {
                    alert('Pilih minimal satu lokasi.');
                    return;
                }
                if (!confirm((active ? 'Aktifkan ' : 'Nonaktifkan ') + ids.length + ' lokasi?')) return;

                $('#bulkActive').val(active ? 1 : 0);
                $('#bulkIds').html(ids.map(function (id) {
                    return '<input type="hidden" name="ids[]" value="' + id + '">';
                }).join(''));
                $('#bulkForm').trigger('submit');
            }

            $('#btnBulkActive').on('click', function () { bulkToggle(true); });
            $('#btnBulkInactive').on('click', function () { bulkToggle(false); });
        });
    </script>
</body>
</html>