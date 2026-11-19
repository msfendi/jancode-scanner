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
                                <i class="fas fa-plus-circle text-primary mr-1"></i> Tambah Pallet
                            </h1>
                            <p class="mb-0 text-muted small">Kode dibuat otomatis melanjutkan nomor terakhir.</p>
                        </div>
                        <a href="{{ route('pallets.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali
                        </a>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0 pl-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <form method="POST" action="{{ route('pallets.store') }}">
                                @csrf

                                <div class="form-group">
                                    <label class="small font-weight-bold">Kode pallet</label>
                                    <input type="text" class="form-control form-control-sm bg-light" value="{{ $nextCode }}" readonly>
                                    <small class="text-muted">Kode final ditetapkan sistem saat disimpan.</small>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label class="small font-weight-bold">Status</label>
                                        <select name="status" class="form-control form-control-sm">
                                            @foreach ($statuses as $key => $label)
                                                <option value="{{ $key }}" {{ old('status', 'available') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label class="small font-weight-bold">Kapasitas karton</label>
                                        <input type="number" name="max_carton" class="form-control form-control-sm"
                                            value="{{ old('max_carton', 42) }}" min="1" max="999" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label class="small font-weight-bold">Zona</label>
                                        <input type="text" name="zone" class="form-control form-control-sm"
                                            value="{{ old('zone') }}" maxlength="20" placeholder="opsional">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="small font-weight-bold">Catatan</label>
                                    <input type="text" name="notes" class="form-control form-control-sm"
                                        value="{{ old('notes') }}" maxlength="255" placeholder="opsional">
                                </div>

                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-save mr-1"></i> Simpan
                                </button>
                                <a href="{{ route('pallets.index') }}" class="btn btn-sm btn-secondary">Batal</a>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
            @include('layout.footer')
</body>
</html>