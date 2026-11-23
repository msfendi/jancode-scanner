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
                                <i class="fas fa-pallet text-primary mr-1"></i> Pallet
                                <code>{{ $pallet->code }}</code>
                            </h1>
                            <p class="mb-0 text-muted small">Halaman tujuan scan QR pallet.</p>
                        </div>
                        <div>
                            <a href="{{ route('pallets.index') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-1"></i> Kembali
                            </a>
                            <a href="{{ route('pallets.labels', ['ids' => [$pallet->id]]) }}" target="_blank"
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
                                    <img src="{{ $qr }}" alt="QR {{ $pallet->code }}" style="width:220px;height:220px">
                                    <h5 class="font-weight-bold mt-2 mb-1">{{ $pallet->code }}</h5>
                                    <p class="small text-muted mb-3">Isi QR: <code>{{ $pallet->code }}</code></p>
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
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8 mb-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header py-2 bg-white">
                                    <h6 class="m-0 font-weight-bold text-primary">Informasi Pallet</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr><th style="width:180px">Kode</th><td>{{ $pallet->code }}</td></tr>
                                        <tr><th>Status</th><td>{{ $pallet->status_label }}</td></tr>
                                        <tr><th>Kapasitas</th><td>{{ $pallet->max_carton }} karton</td></tr>
                                        <tr><th>Zona</th><td>{{ $pallet->zone ?: '-' }}</td></tr>
                                        <tr><th>Catatan</th><td>{{ $pallet->notes ?: '-' }}</td></tr>
                                        <tr><th>Dibuat</th><td>{{ $pallet->created_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                                        <tr>
                                            <th>Lokasi sekarang</th>
                                            <td><span class="text-muted">Belum terhubung ke lokasi (fase penempatan berikutnya).</span></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    @can('pallet.manage')
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header py-2 bg-white">
                            <h6 class="m-0 font-weight-bold text-primary">Edit Pallet</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('pallets.update', $pallet->id) }}">
                                @csrf
                                @method('PUT')
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label class="small font-weight-bold">Status</label>
                                        <select name="status" class="form-control form-control-sm">
                                            @foreach ($statuses as $key => $label)
                                                <option value="{{ $key }}" {{ $pallet->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label class="small font-weight-bold">Kapasitas</label>
                                        <input type="number" name="max_carton" class="form-control form-control-sm"
                                            value="{{ $pallet->max_carton }}" min="1" max="999" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label class="small font-weight-bold">Zona</label>
                                        <input type="text" name="zone" class="form-control form-control-sm"
                                            value="{{ $pallet->zone }}" maxlength="20">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label class="small font-weight-bold">Catatan</label>
                                        <input type="text" name="notes" class="form-control form-control-sm"
                                            value="{{ $pallet->notes }}" maxlength="255">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                            </form>
                        </div>
                    </div>
                    @endcan

                </div>
            </div>
            @include('layout.footer')
</body>
</html>