<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
@include('layout.sidebar')
    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">
            @include('layout.navbar')
            <!-- Begin Page Content -->
            <div class="container-fluid">

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h1 class="h3 mb-0 text-gray-800">Assign Role User</h1>
                        <p class="mb-0 text-muted small">
                            Pilih role untuk user. Hak akses mengikuti permission yang dimiliki role tersebut.
                        </p>
                    </div>
                    <a href="{{ route('user.index') }}" class="btn btn-sm btn-outline-secondary shadow-sm">
                        <i class="fas fa-arrow-left fa-sm mr-1"></i> Kembali
                    </a>
                </div>

                <!-- Approach -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Form Assign Role User</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('user.assignrole') }}">
                            @csrf
                            @if ($message = Session::get('success'))
                            <div class="alert alert-success alert-block">
                                <button type="button" class="close" data-dismiss="alert">×</button>
                                <strong>{{ $message }}</strong>
                            </div>
                            @endif

                            @if ($message = Session::get('error'))
                            <div class="alert alert-danger alert-block">
                                <button type="button" class="close" data-dismiss="alert">×</button>
                                <strong>{{ $message }}</strong>
                            </div>
                            @endif

                            <input type="hidden" id="id" name="id" value="{{ $user->id }}">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>ID :</label>
                                    <input class="form-control" type="text" value="{{ $user->id }}" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Nama Lengkap :</label>
                                    <input class="form-control" type="text" value="{{ $user->name }}" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Email :</label>
                                    <input class="form-control" type="email" value="{{ $user->email }}" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Dept :</label>
                                    <input class="form-control" type="text" value="{{ $user->dept }}" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Role :</label>
                                    <select class="form-control" id="role_id" name="role_id" required>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}"
                                                @selected($user->roles->contains('id', $role->id))>
                                                {{ \App\Support\Rbac::roleLabel($role->name) }} ({{ $role->name }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">
                                        Role menentukan modul mana yang bisa diakses user.
                                    </small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-save mr-1"></i> Update Role
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Ringkasan permission role -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Permission per Role</h6>
                    </div>
                    <div class="card-body">
                        @foreach ($roles as $role)
                            @php $rolePerms = $role->permissions->pluck('name'); @endphp
                            <div class="mb-3">
                                <div class="font-weight-bold mb-1">
                                    {{ \App\Support\Rbac::roleLabel($role->name) }}
                                    <code class="small ml-1">{{ $role->name }}</code>
                                    <span class="badge badge-primary ml-1">{{ $rolePerms->count() }}</span>
                                </div>
                                <div class="small text-muted mb-1">{{ \App\Support\Rbac::roleDescription($role->name) }}</div>
                                <div>
                                    @forelse ($rolePerms as $perm)
                                        <code class="badge badge-light border mr-1 mb-1">{{ $perm }}</code>
                                    @empty
                                        <span class="text-muted small">Belum ada permission.</span>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

@include('layout.footer')
</body>
</html>
