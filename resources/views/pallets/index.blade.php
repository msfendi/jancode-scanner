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
                                <i class="fas fa-pallet text-primary mr-1"></i> Manage Pallet
                            </h1>
                            <p class="mb-0 text-muted small">
                                Master pallet (kode + QR). Penempatan ke lokasi dikelola pada fase berikutnya.
                            </p>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge badge-light border mr-2 py-2 px-3">
                                Total <strong class="text-primary">{{ $pallets->count() }}</strong> pallet
                            </span>
                            @can('pallet.manage')
                                <a href="{{ route('pallets.create') }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus mr-1"></i> Tambah Pallet
                                </a>
                            @endcan
                        </div>
                    </div>

                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    @endif

                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-body py-3">
                            <div class="row align-items-end">
                                <div class="col-md-3 mb-2 mb-md-0">
                                    <label class="small font-weight-bold text-gray-600 mb-1">Status</label>
                                    <select id="filterStatus" class="form-control form-control-sm">
                                        <option value="">Semua status</option>
                                        @foreach ($statuses as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-9 text-md-right">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnPrintSelected">
                                        <i class="fas fa-print mr-1"></i> Cetak label terpilih
                                    </button>
                                </div>
                            </div>

                            <hr class="my-3">

                            <form method="GET" action="{{ route('pallets.labels') }}" target="_blank" class="form-inline">
                                <span class="small font-weight-bold text-gray-600 mr-2">Cetak range:</span>
                                <input type="text" name="from" class="form-control form-control-sm mr-2 mb-1"
                                    placeholder="dari, mis. PLT-0001" value="{{ request('from') }}">
                                <input type="text" name="to" class="form-control form-control-sm mr-2 mb-1"
                                    placeholder="sampai, mis. PLT-0100" value="{{ request('to') }}">
                                <button type="submit" class="btn btn-sm btn-primary mb-1">
                                    <i class="fas fa-print mr-1"></i> Cetak
                                </button>
                            </form>

                            <hr class="my-3">

                            <!-- Cetak stiker pasangan (QR lokasi + QR pallet dalam satu stiker 15 x 5 cm) -->
                            <form method="GET" action="{{ route('pallet.pairLabels') }}" target="_blank" class="form-inline">
                                <span class="small font-weight-bold text-gray-600 mr-2">Stiker pasangan:</span>
                                <input type="text" name="from" class="form-control form-control-sm mr-2 mb-1"
                                    placeholder="dari, mis. PLT-0001">
                                <input type="text" name="to" class="form-control form-control-sm mr-2 mb-1"
                                    placeholder="sampai, mis. PLT-0050">
                                <button type="submit" class="btn btn-sm btn-outline-success mb-1">
                                    <i class="fas fa-qrcode mr-1"></i> Cetak stiker
                                </button>
                                <small class="text-muted ml-2 mb-1">
                                    Hanya pallet yang sudah terikat ke lokasi
                                </small>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-hover" id="tablePallets" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width:32px"><input type="checkbox" id="checkAll"></th>
                                            <th>Kode</th>
                                            <th>Status</th>
                                            <th class="text-right">Kapasitas</th>
                                            <th>Zona</th>
                                            <th>Catatan</th>
                                            <th style="width:150px" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($pallets as $pallet)
                                            <tr>
                                                <td><input type="checkbox" class="row-check" value="{{ $pallet->id }}"></td>
                                                <td><code class="font-weight-bold">{{ $pallet->code }}</code></td>
                                                <td data-status="{{ $pallet->status }}">
                                                    @switch($pallet->status)
                                                        @case('available')
                                                            <span class="badge badge-success">Tersedia</span>
                                                            @break
                                                        @case('in_use')
                                                            <span class="badge badge-primary">Dipakai</span>
                                                            @break
                                                        @case('maintenance')
                                                            <span class="badge badge-warning">Perawatan</span>
                                                            @break
                                                        @default
                                                            <span class="badge badge-secondary">Pensiun</span>
                                                    @endswitch
                                                </td>
                                                <td class="text-right">{{ $pallet->max_carton }}</td>
                                                <td>{{ $pallet->zone ?: '-' }}</td>
                                                <td class="small text-muted">{{ $pallet->notes ?: '-' }}</td>
                                                <td class="text-center text-nowrap">
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-dark btn-qr"
                                                        title="Lihat QR"
                                                        data-code="{{ $pallet->code }}"
                                                        data-qr="{{ route('pallets.qr', $pallet->id) }}">
                                                        <i class="fas fa-qrcode"></i>
                                                    </button>
                                                    <a href="{{ route('pallets.show', $pallet->id) }}" class="btn btn-sm btn-outline-primary" title="Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    @can('pallet.manage')
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-secondary btn-edit"
                                                            title="Edit"
                                                            data-id="{{ $pallet->id }}"
                                                            data-code="{{ $pallet->code }}"
                                                            data-status="{{ $pallet->status }}"
                                                            data-max="{{ $pallet->max_carton }}"
                                                            data-zone="{{ $pallet->zone }}"
                                                            data-notes="{{ $pallet->notes }}">
                                                            <i class="fas fa-pen"></i>
                                                        </button>
                                                    @endcan
                                                    <a href="{{ route('pallets.labels', ['ids' => [$pallet->id]]) }}" target="_blank"
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

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

    <!-- Modal QR -->
    <div class="modal fade" id="qrModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title font-weight-bold">QR Pallet <span id="qrCodeLabel"></span></h6>
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

    @can('pallet.manage')
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form method="POST" id="editForm">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title font-weight-bold">Edit Pallet <span id="editCodeLabel"></span></h6>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="small font-weight-bold">Status</label>
                            <select name="status" id="editStatus" class="form-control form-control-sm">
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Kapasitas karton</label>
                            <input type="number" name="max_carton" id="editMax" class="form-control form-control-sm" min="1" max="999" required>
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Zona</label>
                            <input type="text" name="zone" id="editZone" class="form-control form-control-sm" maxlength="20">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Catatan</label>
                            <input type="text" name="notes" id="editNotes" class="form-control form-control-sm" maxlength="255">
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

    <script>
        $(function () {
            var table = $('#tablePallets').DataTable({
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Semua']],
                order: [[1, 'asc']],
                columnDefs: [{ orderable: false, targets: [0, 6] }],
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampil _MENU_ baris',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ pallet',
                    infoEmpty: 'Tidak ada data',
                    zeroRecords: 'Tidak ada pallet yang cocok',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
                }
            });

            $.fn.dataTable.ext.search.push(function (settings, data) {
                var status = $('#filterStatus').val();
                if (!status) return true;
                return data[2].trim().toLowerCase().indexOf(
                    $('#filterStatus option:selected').text().toLowerCase()
                ) !== -1;
            });

            $('#filterStatus').on('change', function () { table.draw(); });

            $('#checkAll').on('change', function () {
                $('.row-check').prop('checked', $(this).prop('checked'));
            });

            function selectedIds() {
                return $('.row-check:checked').map(function () { return this.value; }).get();
            }

            $(document).on('click', '.btn-qr', function () {
                var btn = $(this);
                $('#qrCodeLabel').text(btn.data('code'));
                $('#qrPayload').text(btn.data('code'));
                $('#qrImage').attr('src', btn.data('qr'));
                $('#qrDownload').attr('href', btn.data('qr'));
                $('#qrModal').modal('show');
            });

            @can('pallet.manage')
            $(document).on('click', '.btn-edit', function () {
                var btn = $(this);
                $('#editCodeLabel').text(btn.data('code'));
                $('#editStatus').val(btn.data('status'));
                $('#editMax').val(btn.data('max'));
                $('#editZone').val(btn.data('zone'));
                $('#editNotes').val(btn.data('notes'));
                $('#editForm').attr('action', '{{ url('pallets') }}/' + btn.data('id'));
                $('#editModal').modal('show');
            });
            @endcan

            $('#btnPrintSelected').on('click', function () {
                var ids = selectedIds();
                if (!ids.length) {
                    alert('Pilih minimal satu pallet.');
                    return;
                }
                var url = '{{ route('pallets.labels') }}?' + ids.map(function (id) {
                    return 'ids[]=' + encodeURIComponent(id);
                }).join('&');
                window.open(url, '_blank');
            });
        });
    </script>
</body>
</html>