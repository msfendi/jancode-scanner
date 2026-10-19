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

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h1 class="h4 mb-0 text-gray-800">
                                <i class="fas fa-play-circle text-primary mr-1"></i> Sesi Import
                            </h1>
                            <p class="mb-0 text-muted small">
                                Langkah <strong>1</strong>: mulai session sebagai induk. Satu session mengikat satu
                                <strong>style / OCF / destination / warehouse</strong>.
                            </p>
                        </div>
                        <a href="{{ route('jancode.index') }}" class="btn btn-sm btn-outline-primary shadow-sm">
                            <i class="fas fa-file-import fa-sm mr-1"></i> Ke Import Packing List
                        </a>
                    </div>

                    @include('layout.workflow-steps', ['current' => 'session'])

                    @if ($current)
                        @include('layout.session-banner', ['currentSession' => $current])
                    @endif

                    <div class="row">
                        <!-- Start Session -->
                        <div class="col-lg-5 mb-4">
                            <div class="card border-0 shadow-lg session-hero h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="session-hero-icon mr-3">
                                            <i class="fas fa-layer-group"></i>
                                        </div>
                                        <div>
                                            <h5 class="text-white mb-0 font-weight-bold">Mulai Session Baru</h5>
                                            <div class="text-white-50 small">Kode session dibuat otomatis</div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('session.start') }}" id="startSessionForm">
                                        @csrf
                                        <div class="form-group mb-3">
                                            <label class="text-white-50 small font-weight-bold text-uppercase mb-1">
                                                Catatan <span class="text-white-50">(opsional)</span>
                                            </label>
                                            <textarea name="notes" id="sessionNotes" rows="3" maxlength="1000"
                                                class="form-control border-0 shadow-sm"
                                                placeholder="Contoh: Packing list buyer A, shift pagi, PO 12345...">{{ old('notes') }}</textarea>
                                        </div>

                                        <div class="session-code-preview mb-3">
                                            <div class="text-white-50 small text-uppercase font-weight-bold mb-1">Format kode</div>
                                            <code class="text-white">IMP-{{ now()->format('Ymd-His') }}-###</code>
                                        </div>

                                        <button type="submit" class="btn btn-light btn-block font-weight-bold text-primary shadow-sm">
                                            <i class="fas fa-play mr-1"></i> Start Session
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="col-lg-7 mb-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body p-4">
                                    <h6 class="font-weight-bold text-gray-800 mb-3">
                                        <i class="fas fa-info-circle text-primary mr-1"></i> Kenapa perlu session?
                                    </h6>
                                    <div class="row">
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex">
                                                <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                                                <div>
                                                    <div class="font-weight-bold small">Pemisah file identik</div>
                                                    <div class="text-muted small">
                                                        Dua file dengan style/OCF/destination/warehouse sama tetap terpisah
                                                        karena berada di session berbeda.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex">
                                                <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                                                <div>
                                                    <div class="font-weight-bold small">Satu induk data</div>
                                                    <div class="text-muted small">
                                                        Import, manage proforma, scan, dan pallet menempel pada session.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex">
                                                <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                                                <div>
                                                    <div class="font-weight-bold small">Revisi otomatis</div>
                                                    <div class="text-muted small">
                                                        Beberapa file Excel dalam session yang sama disimpan sebagai revisi.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex">
                                                <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                                                <div>
                                                    <div class="font-weight-bold small">Riwayat aman</div>
                                                    <div class="text-muted small">
                                                        Tutup session setelah selesai; data tetap bisa dibuka kembali.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-light border mb-0 py-2">
                                        <div class="small text-muted">
                                            <strong>Alur:</strong>
                                            <span class="text-primary font-weight-bold">Start Session</span>
                                            <i class="fas fa-chevron-right mx-1 small"></i> Import Excel
                                            <i class="fas fa-chevron-right mx-1 small"></i> Manage Proforma
                                            <i class="fas fa-chevron-right mx-1 small"></i> Scan
                                            <i class="fas fa-chevron-right mx-1 small"></i> Pallet
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Session -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-history mr-1"></i> Daftar Session
                                </h6>
                                <span class="badge badge-light border">{{ $sessions->count() }} session</span>
                            </div>
                            <form method="GET" action="{{ route('session.index') }}" class="form-row align-items-end">
                                <div class="col-md-6 mb-1">
                                    <label class="small text-muted mb-0">
                                        Cari kode / style / OCF / destination / warehouse / catatan
                                    </label>
                                    <input type="text" name="q" value="{{ $search }}"
                                        class="form-control form-control-sm"
                                        placeholder="mis. TESTA1 / 90001 / JAPAN / WH1">
                                </div>
                                <div class="col-md-3 mb-1">
                                    <label class="small text-muted mb-0">Status</label>
                                    <select name="status" class="form-control form-control-sm">
                                        <option value="">Semua status</option>
                                        <option value="open" {{ $status === 'open' ? 'selected' : '' }}>OPEN</option>
                                        <option value="closed" {{ $status === 'closed' ? 'selected' : '' }}>CLOSED</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-1">
                                    <button type="submit" class="btn btn-sm btn-primary btn-block">
                                        <i class="fas fa-search mr-1"></i> Cari
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 align-middle">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="pl-3">Kode Session</th>
                                            <th>Style</th>
                                            <th>OCF / CPO</th>
                                            <th>Destination</th>
                                            <th>Warehouse</th>
                                            <th class="text-center">Revisi</th>
                                            <th class="text-center">Packing List</th>
                                            <th>Catatan</th>
                                            <th>Status</th>
                                            <th class="text-right pr-3">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($sessions as $s)
                                            @php $isCurrent = $current && $current->id === $s->id; @endphp
                                            <tr class="{{ $isCurrent ? 'table-primary' : '' }}">
                                                <td class="pl-3 align-middle text-nowrap">
                                                    <div class="font-weight-bold text-gray-800">{{ $s->code }}</div>
                                                    <div class="text-muted" style="font-size:.72rem;">
                                                        {{ $s->started_at ? $s->started_at->format('d/m/Y H:i') : '-' }}
                                                        @if ($s->user)
                                                            &middot; {{ $s->user->name }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="align-middle">
                                                    <span class="small text-gray-800">{{ $s->style_number ?: '-' }}</span>
                                                </td>
                                                <td class="align-middle">
                                                    <span class="small text-gray-800">{{ $s->ocf_number ?: '-' }}</span>
                                                </td>
                                                <td class="align-middle">
                                                    <span class="small text-gray-800">{{ $s->destination ?: '-' }}</span>
                                                </td>
                                                <td class="align-middle">
                                                    <span class="small text-gray-800">{{ $s->warehouse ?: '-' }}</span>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <span class="badge badge-light border">{{ $s->import_batches_count }}</span>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <span class="badge badge-light border">{{ $s->packing_lists_count }}</span>
                                                </td>
                                                <td class="align-middle">
                                                    <div class="text-muted small text-truncate" style="max-width: 220px;"
                                                        title="{{ $s->notes }}">
                                                        {{ $s->notes ?: '-' }}
                                                    </div>
                                                </td>
                                                <td class="align-middle">
                                                    @if ($s->isOpen())
                                                        <span class="badge badge-success">OPEN</span>
                                                    @else
                                                        <span class="badge badge-secondary">CLOSED</span>
                                                    @endif
                                                </td>
                                                <td class="align-middle text-right pr-3 text-nowrap">
                                                    @if (!$isCurrent)
                                                        <form method="POST" action="{{ route('session.select', $s) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-primary"
                                                                title="Jadikan session aktif">
                                                                <i class="fas fa-hand-pointer mr-1"></i> Gunakan
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="badge badge-primary px-2 py-1">
                                                            <i class="fas fa-check mr-1"></i> Aktif
                                                        </span>
                                                    @endif

                                                    @if ($s->isClosed())
                                                        <form method="POST" action="{{ route('session.reopen', $s) }}" class="d-inline"
                                                            onsubmit="return confirm('Buka kembali session {{ $s->code }} untuk melanjutkan pekerjaan?');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success"
                                                                title="Buka kembali session (pekerjaan belum selesai)">
                                                                <i class="fas fa-undo mr-1"></i> Buka Kembali
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('session.close', $s) }}" class="d-inline"
                                                            onsubmit="return confirm('Tutup session {{ $s->code }}? Import & scan baru tidak akan diizinkan.');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                                title="Tutup session">
                                                                <i class="fas fa-stop-circle"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="10" class="text-center text-muted py-4">
                                                    @if ($search !== '' || $status)
                                                        Tidak ada session yang cocok dengan pencarian.
                                                    @else
                                                        Belum ada session. Mulai session pertama Anda di atas.
                                                    @endif
                                                </td>
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

        </div>
    </div>

    <style>
        .session-hero {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 60%, #1b3a9c 100%);
            border-radius: .75rem;
        }
        .session-hero-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .18);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.2rem;
        }
        .session-code-preview code {
            background: rgba(0, 0, 0, .18);
            padding: .3rem .55rem;
            border-radius: .35rem;
            font-size: .9rem;
            letter-spacing: .03em;
        }
        .session-hero textarea::placeholder {
            color: #b7c4e8;
        }
    </style>

</body>
</html>
