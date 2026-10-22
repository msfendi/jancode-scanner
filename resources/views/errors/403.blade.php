<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body id="page-top">
<div id="wrapper">
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content" class="d-flex align-items-center justify-content-center" style="min-height: 70vh;">
            <div class="container-fluid">
                <div class="row justify-content-center">
                    <div class="col-xl-6 col-lg-8 col-md-10">
                        <div class="card border-left-danger shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-lock fa-3x text-danger mb-3"></i>

                                <h1 class="h4 text-gray-800 mb-2">403 — Akses Ditolak</h1>

                                <p class="text-muted mb-3">
                                    {{ $exception?->getMessage() ?: 'Anda tidak memiliki hak akses untuk halaman ini.' }}
                                </p>

                                <div class="alert alert-light border small text-left mb-4">
                                    <div class="font-weight-bold mb-1">
                                        <i class="fas fa-user-shield mr-1 text-primary"></i>
                                        Hak akses Anda:
                                    </div>
                                    @php $roleNames = auth()->user()?->roles?->pluck('name') ?? collect(); @endphp
                                    @forelse ($roleNames as $roleName)
                                        <div class="mb-1">
                                            <strong>{{ \App\Support\Rbac::roleLabel($roleName) }}</strong>
                                            <code class="small ml-1">{{ $roleName }}</code>
                                            <div class="text-muted small">
                                                {{ \App\Support\Rbac::roleDescription($roleName) }}
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-muted small">User ini belum punya role. Hubungi Super Admin.</div>
                                    @endforelse
                                    <div class="text-muted small mt-2">
                                        Permission bisa diubah oleh Super Admin pada halaman
                                        <strong>Role &amp; Permission</strong>.
                                    </div>
                                </div>

                                <a href="{{ route('home') }}" class="btn btn-primary">
                                    <i class="fas fa-home mr-1"></i> Kembali ke Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
