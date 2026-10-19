@php
    $sessionBanner = $currentSession ?? \App\Models\ImportSession::current();
@endphp

@if ($sessionBanner)
    <div class="card shadow-sm border-0 mb-3 session-banner">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <span class="session-pill">
                        <i class="fas fa-layer-group mr-1"></i>{{ $sessionBanner->code }}
                    </span>
                    <div class="ml-3">
                        <div class="small font-weight-bold text-gray-800">
                            {{ $sessionBanner->group_label }}
                        </div>
                        <div class="text-muted text-truncate" style="font-size:.75rem; max-width: 520px;">
                            {{ $sessionBanner->notes ?: 'Tanpa catatan' }}
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    @if ($sessionBanner->isOpen())
                        <span class="badge badge-success mr-2">OPEN</span>
                    @else
                        <span class="badge badge-secondary mr-2">CLOSED</span>
                        <form method="POST" action="{{ route('session.reopen', $sessionBanner) }}" class="d-inline mr-2"
                            onsubmit="return confirm('Buka kembali session {{ $sessionBanner->code }} untuk melanjutkan pekerjaan?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="fas fa-undo mr-1"></i> Buka Kembali
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('session.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-exchange-alt mr-1"></i> Ganti Session
                    </a>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-warning border-left-warning shadow-sm mb-3 py-2">
        <div class="d-flex align-items-center justify-content-between">
            <div class="small">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Belum ada session aktif. Mulai session dulu sebelum import packing list.
            </div>
            <a href="{{ route('session.index') }}" class="btn btn-sm btn-warning font-weight-bold">
                <i class="fas fa-play mr-1"></i> Mulai Session
            </a>
        </div>
    </div>
@endif

<style>
    .session-pill {
        display: inline-flex;
        align-items: center;
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        color: #fff;
        font-weight: 700;
        font-size: .78rem;
        letter-spacing: .02em;
        padding: .35rem .7rem;
        border-radius: 999px;
        white-space: nowrap;
    }
</style>
