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
                                <i class="fas fa-map-marker-alt text-primary mr-1"></i>
                                Lokasi <code>{{ $location->code }}</code>
                            </h1>
                            <p class="mb-0 text-muted small">Halaman tujuan scan QR lokasi.</p>
                        </div>
                        <div>
                            <a href="{{ route('locations.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-1"></i> Kembali
                            </a>
                            <a href="{{ route('locations.labels', ['ids' => [$location->id]]) }}" target="_blank"
                                class="btn btn-sm btn-outline-success">
                                <i class="fas fa-print mr-1"></i> Cetak Label
                            </a>
                        </div>
                    </div>

                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-lg-4 mb-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body text-center">
                                    <img src="{{ $qr }}" alt="QR {{ $location->code }}" style="width:220px;height:220px">
                                    <h5 class="font-weight-bold mt-2 mb-1">{{ $location->code }}</h5>
                                    <p class="small text-muted mb-3">Isi QR: <code>{{ $location->code }}</code></p>
                                    <span class="badge {{ $location->is_active ? 'badge-success' : 'badge-secondary' }}">
                                        {{ $location->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8 mb-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header py-2 bg-white">
                                    <h6 class="m-0 font-weight-bold text-primary">Informasi Lokasi</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr><th style="width:180px">Row</th><td>{{ $location->row_label }}</td></tr>
                                        <tr><th>Kolom</th><td>{{ $location->col_label }}</td></tr>
                                        <tr><th>Posisi</th><td>{{ $location->position_no }}</td></tr>
                                        <tr><th>Kapasitas</th><td>{{ $location->max_carton }} karton</td></tr>
                                        <tr><th>Mode negara</th><td>{{ $location->country_mode }}</td></tr>
                                        <tr>
                                            <th>Pallet terisi</th>
                                            <td>
                                                @if ($assignment)
                                                    {{ $assignment->style ?: '-' }} / {{ $assignment->ocf ?: '-' }}
                                                    <span class="text-muted">({{ $assignment->total_carton }} karton, {{ $assignment->country ?: '-' }})</span>
                                                @else
                                                    <span class="text-muted">Kosong</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    @can('location.manage')
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header py-2 bg-white">
                            <h6 class="m-0 font-weight-bold text-primary">Edit Lokasi</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('locations.update', $location->id) }}" class="form-inline">
                                @csrf
                                @method('PUT')
                                <div class="form-group mr-3 mb-2">
                                    <label class="small font-weight-bold mr-2">Kapasitas</label>
                                    <input type="number" name="max_carton" value="{{ $location->max_carton }}"
                                        min="1" max="999" class="form-control form-control-sm" style="width:100px" required>
                                </div>
                                <div class="form-group mr-3 mb-2">
                                    <label class="small font-weight-bold mr-2">Mode</label>
                                    <select name="country_mode" class="form-control form-control-sm">
                                        <option value="single" {{ $location->country_mode === 'single' ? 'selected' : '' }}>single</option>
                                        <option value="multi" {{ $location->country_mode === 'multi' ? 'selected' : '' }}>multi</option>
                                    </select>
                                </div>
                                <div class="custom-control custom-switch mr-3 mb-2">
                                    <input type="checkbox" class="custom-control-input" id="isActive" name="is_active" value="1"
                                        {{ $location->is_active ? 'checked' : '' }}>
                                    <label class="custom-control-label small" for="isActive">Aktif</label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary mb-2">Simpan</button>
                            </form>
                        </div>
                    </div>
                    @endcan

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header py-2 bg-white">
                            <h6 class="m-0 font-weight-bold text-primary">
                                Karton di lokasi ini
                                <span class="badge badge-light border ml-1">{{ $assignment? $assignment->details->count() : 0 }} baris</span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Jancode</th>
                                            <th>Size</th>
                                            <th>Color</th>
                                            <th class="text-right">Qty karton</th>
                                            <th class="text-right">Qty/pcs</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($assignment? $assignment->details : [] as $detail)
                                            <tr>
                                                <td><code>{{ $detail->jancode->jancode ?? '-' }}</code></td>
                                                <td>{{ $detail->jancode->size ?? '-' }}</td>
                                                <td>{{ $detail->jancode->color ?? '-' }}</td>
                                                <td class="text-right">{{ $detail->qty_carton }}</td>
                                                <td class="text-right">{{ $detail->jancode->qty_per_carton ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">Belum ada karton.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            @include('layout.footer')
</body>
</html>