<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body id="page-top">
<!-- Page Wrapper -->
@include('sweetalert::alert')
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
                        <h1 class="h3 mb-0 text-gray-800">Role &amp; Permission</h1>
                        <p class="mb-0 text-muted small">
                            Kelola hak akses per modul. Role <strong>Super Admin</strong> memiliki akses penuh,
                            role <strong>CTPAT</strong> kecuali RBAC, User Management, dan hapus permanen session.
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('role.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Role
                        </a>
                    </div>
                </div>

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

                <!-- Daftar Role -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user-shield mr-1"></i> Daftar Role
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle" id="dataTable" width="100%" cellspacing="0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:60px;">ID</th>
                                        <th>Role</th>
                                        <th style="width:150px;">Permission</th>
                                        <th style="width:220px;">Keterangan</th>
                                        <th style="width:120px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($roles as $role)
                                        @php
                                            $isProtected = in_array($role->name, $protected, true);
                                        @endphp
                                        <tr>
                                            <td>{{ $role->id }}</td>
                                            <td>
                                                <strong>{{ \App\Support\Rbac::roleLabel($role->name) }}</strong>
                                                <code class="small ml-1">{{ $role->name }}</code>
                                                @if ($isProtected)
                                                    <span class="badge badge-info ml-1">protected</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-primary">{{ $role->permissions->count() }}</span>
                                            </td>
                                            <td class="small text-muted">{{ \App\Support\Rbac::roleDescription($role->name) }}</td>
                                            <td class="text-center text-nowrap">
                                                <button type="button" class="btn btn-info btn-circle btn-sm btn-edit-permission"
                                                    data-id="{{ $role->id }}"
                                                    data-name="{{ $role->name }}"
                                                    data-perms="{{ json_encode($role->permissions->pluck('name')->values()) }}">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                @if (! $isProtected)
                                                    <a class="btn btn-danger btn-circle btn-sm btn-delete-record"
                                                        data-delete-link="{{ route('role.delete', ['id' => $role->id]) }}"
                                                        data-delete-name="{{ $role->name }}"
                                                        data-toggle="modal" data-target="#deleteModal">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Matrix Permission -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-table mr-1"></i> Matrix Permission per Role
                        </h6>
                        <span class="small text-muted">Centang permission yang diizinkan untuk tiap role.</span>
                    </div>
                    <div class="card-body">
                        @foreach ($roles as $role)
                            @php
                                $rolePerms = $role->permissions->pluck('name')->all();
                            @endphp
                            <div class="mb-4 pb-3 border-bottom">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-user-tag mr-1 text-primary"></i>
                                        {{ \App\Support\Rbac::roleLabel($role->name) }}
                                        <code class="small ml-1">{{ $role->name }}</code>
                                    </h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-toggle-all"
                                        data-target="perm-{{ $role->id }}">
                                        <i class="fas fa-check-double mr-1"></i> Pilih semua
                                    </button>
                                </div>

                                <form method="POST" action="{{ route('role.permissions', ['id' => $role->id]) }}">
                                    @csrf
                                    <div class="row">
                                        @foreach ($groups as $group => $permissions)
                                            <div class="col-md-6 col-xl-4 mb-3">
                                                <div class="border rounded p-2 h-100">
                                                    <div class="font-weight-bold small text-uppercase text-gray-600 mb-2">
                                                        {{ $group }}
                                                    </div>
                                                    @foreach ($permissions as $permName => $permLabel)
                                                        <div class="custom-control custom-checkbox mb-1">
                                                            <input class="custom-control-input perm-check" type="checkbox"
                                                                id="perm-{{ $role->id }}-{{ $permName }}"
                                                                name="permissions[]" value="{{ $permName }}"
                                                                data-target="perm-{{ $role->id }}"
                                                                @checked(in_array($permName, $rolePerms, true))>
                                                            <label class="custom-control-label small" for="perm-{{ $role->id }}-{{ $permName }}">
                                                                {{ $permLabel }}
                                                                <code class="small text-muted d-block">{{ $permName }}</code>
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-save mr-1"></i> Simpan Permission
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Peta Route -> Permission -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-route mr-1"></i> Peta Route & Permission ({{ count($routeMap) }} route)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                            <table class="table table-sm table-bordered mb-0" style="font-size: 12px;">
                                <thead class="thead-light" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th style="width: 90px;">Method</th>
                                        <th>URI Route</th>
                                        <th style="width: 230px;">Permission</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($routeMap as $route)
                                        <tr>
                                            <td>
                                                @foreach ($route['methods'] as $method)
                                                    <span class="badge badge-secondary mr-1">{{ $method }}</span>
                                                @endforeach
                                            </td>
                                            <td><code>{{ '/' . $route['uri'] }}</code></td>
                                            <td>
                                                <code class="text-primary">{{ $route['permission'] }}</code>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <code>module:xxx</code> = boleh membuka halaman modul (butuh permission
                            <code>xxx.view</code> atau <code>xxx.manage</code>);
                            <code>can:xxx.manage</code> = hak mengubah data pada modul tersebut.
                        </small>
                    </div>
                </div>

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

        <!-- Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="delete-title" class="modal-title">Delete Record</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body"><p id="modal-text-record"></p></div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Tutup</button>
                        <a id="btn-confirm" href=""><button class="btn btn-primary" type="button">Confirm</button></a>
                    </div>
                </div>
            </div>
        </div>

@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<!-- Page level custom scripts -->
<script>
    $('.btn-delete-record').on('click', function () {
        $('#btn-confirm').attr('href', $(this).data('delete-link'));
        $("#modal-text-record").text('Apakah anda yakin ingin menghapus role ' + $(this).data('delete-name') + '?');
    });

    // Pilih / hapus semua permission dalam satu role.
    $(document).on('click', '.btn-toggle-all', function () {
        const target = $(this).data('target');
        const $checks = $('input[data-target="' + target + '"]');
        const allChecked = $checks.length === $checks.filter(':checked').length;
        $checks.prop('checked', !allChecked);
    });

    // Notifikasi permission role saat diklik ikon kunci.
    $(document).on('click', '.btn-edit-permission', function () {
        const name = $(this).data('name');
        const perms = $(this).data('perms') || [];
        const list = Array.isArray(perms) ? perms : JSON.parse(perms);

        const html = '<div class="text-left small">' +
            '<p class="mb-1"><strong>' + name + '</strong> punya ' + list.length + ' permission:</p>' +
            '<div class="p-2 border rounded" style="max-height:220px; overflow:auto;">' +
            (list.length ? list.map(p => '<code class="d-block mb-1">' + p + '</code>').join('') : '<span class="text-muted">Belum ada permission.</span>') +
            '</div></div>';

        Swal.fire({
            icon: 'info',
            title: 'Permission role ' + name,
            html: html,
            confirmButtonText: 'Tutup',
            customClass: { htmlContainer: 'text-left' }
        });
    });
</script>
</html>
