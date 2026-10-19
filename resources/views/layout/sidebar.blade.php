<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ route('home') }}">
        <div class="sidebar-brand-icon">
            <img src="{{ asset('img/chutex.svg') }}" style="width: 40px;">
        </div>
        <div class="sidebar-brand-text mx-3">Chutex <sup>Sys</sup></div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('home') }}">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    @role('Admin')
    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading 1: Sesi Import -->
    <div class="sidebar-heading">
        1. Sesi Import
    </div>

    <li class="nav-item {{ request()->is('session*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('session.index') }}">
            <i class="fas fa-fw fa-play-circle"></i>
            <span>Sesi &amp; Mulai Import</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading 2: Packing List -->
    <div class="sidebar-heading">
        2. Packing List
    </div>

    <li class="nav-item {{ request()->is('jancode*') ? 'active' : '' }}">
        <a class="nav-link {{ request()->is('jancode*') ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapsePackingList"
            aria-expanded="{{ request()->is('jancode*') ? 'true' : 'false' }}" aria-controls="collapsePackingList">
            <i class="fas fa-fw fa-clipboard-list"></i>
            <span>Packing List</span>
        </a>
        <div id="collapsePackingList" class="collapse {{ request()->is('jancode*') ? 'show' : '' }}" aria-labelledby="headingPackingList" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item {{ request()->routeIs('jancode.index') ? 'active' : '' }}" href="{{ route('jancode.index') }}">
                    <i class="fas fa-file-import mr-1"></i> Import Packing List
                </a>
                <a class="collapse-item {{ request()->routeIs('jancode.packingLists*') || request()->routeIs('jancode.importBatchDetail*') ? 'active' : '' }}" href="{{ route('jancode.packingLists') }}">
                    <i class="fas fa-tasks mr-1"></i> Manage Packing List
                </a>
            </div>
        </div>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading 3: Scan Jancode -->
    <div class="sidebar-heading">
        3. Scan Jancode
    </div>

    <li class="nav-item {{ request()->is('scanner*') ? 'active' : '' }}">
        <a class="nav-link {{ request()->is('scanner*') ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapseScanner"
            aria-expanded="{{ request()->is('scanner*') ? 'true' : 'false' }}" aria-controls="collapseScanner">
            <i class="fas fa-fw fa-barcode"></i>
            <span>Scanner Karton</span>
        </a>
        <div id="collapseScanner" class="collapse {{ request()->is('scanner*') ? 'show' : '' }}" aria-labelledby="headingScanner" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item {{ request()->routeIs('scanner.index') ? 'active' : '' }}" href="{{ route('scanner.index') }}">
                    <i class="fas fa-qrcode mr-1"></i> Scan Jancode
                </a>
                <a class="collapse-item {{ request()->routeIs('scanner.batches*') ? 'active' : '' }}" href="{{ route('scanner.batches') }}">
                    <i class="fas fa-file-invoice mr-1"></i> Riwayat Batch Scan
                </a>
            </div>
        </div>
    </li>

    <li class="nav-item {{ request()->is('carton-out*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('cartonOut.index') }}">
            <i class="fas fa-fw fa-box-open"></i>
            <span>Karton Keluar</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading 4: Manage Pallet -->
    <div class="sidebar-heading">
        4. Manage Pallet
    </div>

    <li class="nav-item {{ request()->is('pallet/3d*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('pallet.threeD') }}">
            <i class="fas fa-fw fa-cubes"></i>
            <span>Visualisasi 3D Pallet</span>
        </a>
    </li>
    <li class="nav-item {{ request()->is('pallet') || request()->is('pallet/detail*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('pallet.index') }}">
            <i class="fas fa-fw fa-pallet"></i>
            <span>Manage Pallet Location</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading 5: Hangtag (Modul Terpisah) -->
    <div class="sidebar-heading">
        5. Modul Hangtag
    </div>

    <li class="nav-item {{ request()->is('hangtag*') ? 'active' : '' }}">
        <a class="nav-link {{ request()->is('hangtag*') ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapseHangtag"
            aria-expanded="{{ request()->is('hangtag*') ? 'true' : 'false' }}" aria-controls="collapseHangtag">
            <i class="fas fa-fw fa-tags"></i>
            <span>Hangtag</span>
        </a>
        <div id="collapseHangtag" class="collapse {{ request()->is('hangtag*') ? 'show' : '' }}" aria-labelledby="headingHangtag" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item {{ request()->routeIs('hangtag.index') ? 'active' : '' }}" href="{{ route('hangtag.index') }}">
                    <i class="fas fa-list-alt mr-1"></i> Data Hangtag
                </a>
                <a class="collapse-item {{ request()->routeIs('hangtag.scanner.index') ? 'active' : '' }}" href="{{ route('hangtag.scanner.index') }}">
                    <i class="fas fa-barcode mr-1"></i> Scanner Hangtag
                </a>
            </div>
        </div>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading 6: Pengaturan Sistem -->
    <div class="sidebar-heading">
        6. Pengaturan Sistem
    </div>

    <li class="nav-item {{ request()->is('user*') || request()->is('role*') ? 'active' : '' }}">
        <a class="nav-link {{ request()->is('user*') || request()->is('role*') ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapseUser"
            aria-expanded="{{ request()->is('user*') || request()->is('role*') ? 'true' : 'false' }}" aria-controls="collapseUser">
            <i class="fas fa-fw fa-users-cog"></i>
            <span>User & Hak Akses</span>
        </a>
        <div id="collapseUser" class="collapse {{ request()->is('user*') || request()->is('role*') ? 'show' : '' }}" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item {{ request()->routeIs('user.index') ? 'active' : '' }}" href="{{ route('user.index') }}">
                    <i class="fas fa-users mr-1"></i> Daftar User
                </a>
                <a class="collapse-item {{ request()->routeIs('role.index') ? 'active' : '' }}" href="{{ route('role.index') }}">
                    <i class="fas fa-user-shield mr-1"></i> Daftar Role
                </a>
            </div>
        </div>
    </li>
    @endrole

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
<!-- End of Sidebar -->