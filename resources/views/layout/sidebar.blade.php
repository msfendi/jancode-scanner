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

    <!-- Heading 1: Master & Inbound -->
    <div class="sidebar-heading">
        1. Inbound & Master
    </div>

    <li class="nav-item {{ (request()->is('jancode*') && !request()->routeIs('pallet.*') && !request()->is('scanner*')) ? 'active' : '' }}">
        <a class="nav-link {{ (request()->is('jancode*') && !request()->routeIs('pallet.*') && !request()->is('scanner*')) ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapseMaster"
            aria-expanded="{{ (request()->is('jancode*') && !request()->routeIs('pallet.*') && !request()->is('scanner*')) ? 'true' : 'false' }}" aria-controls="collapseMaster">
            <i class="fas fa-fw fa-boxes"></i>
            <span>Master Jancode</span>
        </a>
        <div id="collapseMaster" class="collapse {{ (request()->is('jancode*') && !request()->routeIs('pallet.*') && !request()->is('scanner*')) ? 'show' : '' }}" aria-labelledby="headingMaster" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item {{ request()->routeIs('jancode.index') ? 'active' : '' }}" href="{{ route('jancode.index') }}">
                    <i class="fas fa-list mr-1"></i> Data Master Jancode
                </a>
                <a class="collapse-item {{ request()->routeIs('jancode.importBatches*') || request()->routeIs('jancode.importBatchDetail*') ? 'active' : '' }}" href="{{ route('jancode.importBatches') }}">
                    <i class="fas fa-history mr-1"></i> Riwayat Import Batch
                </a>
            </div>
        </div>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading 2: Gudang & Pallet -->
    <div class="sidebar-heading">
        2. Gudang & Pallet
    </div>

    <li class="nav-item {{ request()->is('pallet*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('pallet.index') }}">
            <i class="fas fa-fw fa-pallet"></i>
            <span>Manage Pallet Location</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading 3: Operasional Scan -->
    <div class="sidebar-heading">
        3. Operasional Scan
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

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading 4: Hangtag (Modul Terpisah) -->
    <div class="sidebar-heading">
        4. Modul Hangtag
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

    <!-- Heading 5: Pengaturan Sistem -->
    <div class="sidebar-heading">
        5. Pengaturan Sistem
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