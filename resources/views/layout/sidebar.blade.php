<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center">
        <div class="sidebar-brand-icon">
            <img src="{{ asset('img/chutex.svg') }}" style="width: 40px;">
        </div>
        <div class="sidebar-brand-text mx-3">Chutex <sup>Sys</sup></div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item active">
        <a class="nav-link" href="{{ route('home') }}">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span></a>
    </li>

    @role('Admin')
    <li class="nav-item {{ request()->is('user*') || request()->is('role*') ? 'active' : '' }}">
        <a class="nav-link {{ request()->is('user*') || request()->is('role*') ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapseUser"
            aria-expanded="{{ request()->is('user*') || request()->is('role*') ? 'true' : 'false' }}" aria-controls="collapseUser">
            <i class="fas fa-fw fa-users"></i>
            <span>User</span>
        </a>
        <div id="collapseUser" class="collapse {{ request()->is('user*') || request()->is('role*') ? 'show' : '' }}" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item {{ request()->routeIs('user.index') ? 'active' : '' }}" href="{{ route('user.index') }}">Daftar User</a>
                <a class="collapse-item {{ request()->routeIs('role.index') ? 'active' : '' }}" href="{{ route('role.index') }}">Daftar Role</a>
            </div>
        </div>
    </li>
    
    <li class="nav-item {{ request()->is('jancode*') ? 'active' : '' }}">
        <a class="nav-link {{ request()->is('jancode*') ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapseMaster"
            aria-expanded="{{ request()->is('jancode*') ? 'true' : 'false' }}" aria-controls="collapseMaster">
            <i class="fas fa-fw fa-barcode"></i>
            <span>Master Data</span>
        </a>
        <div id="collapseMaster" class="collapse {{ request()->is('jancode*') ? 'show' : '' }}" aria-labelledby="headingMaster" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item {{ request()->routeIs('jancode.index') ? 'active' : '' }}" href="{{ route('jancode.index') }}">Jancode</a>
            </div>
        </div>
    </li>
    @endrole


    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <!-- <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div> -->

</ul>
<!-- End of Sidebar -->