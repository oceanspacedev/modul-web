@auth
<aside class="main-sidebar sidebar-light-primary">
    <a href="/dashboard" class="brand-link">
        <img src="{{ asset('images/logo.svg') }}" alt="" class="brand-logo">
        <span class="brand-text">Modul</span>
    </a>

    <div class="sidebar">
        <nav>
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-header">Menu</li>

                @can('view-dashboard')
                <li class="nav-item">
                    <a href="/dashboard" class="nav-link {{ ($active ?? '') === 'dashboard' ? 'active' : '' }}">
                        <i class="nav-icon fas fa-th-large"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                @endcan

                @can('view-videos')
                <li class="nav-item">
                    <a href="/video" class="nav-link {{ ($active ?? '') === 'video' ? 'active' : '' }}">
                        <i class="nav-icon far fa-play-circle"></i>
                        <p>Video Materi</p>
                    </a>
                </li>
                @endcan

                @canany(['manage-trainings', 'view-my-trainings'])
                <li class="nav-item {{ (($active ?? '') === 'training' || request()->is('training*') || request()->is('my-trainings*')) ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-chalkboard-teacher"></i>
                        <p>
                            Pelatihan
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        @can('manage-trainings')
                        <li class="nav-item">
                            <a href="/training" class="nav-link {{ request()->is('training*') ? 'active' : '' }}">
                                <i class="fas fa-calendar-alt nav-icon"></i>
                                <p>Jadwal Pelatihan</p>
                            </a>
                        </li>
                        @endcan
                        @can('view-my-trainings')
                        <li class="nav-item">
                            <a href="/my-trainings" class="nav-link {{ request()->is('my-trainings*') ? 'active' : '' }}">
                                <i class="fas fa-user-graduate nav-icon"></i>
                                <p>Pelatihan Saya</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                @canany(['manage-learning-modules', 'view-learning-modules'])
                <li class="nav-item {{ (request()->is('learning-module*') || request()->is('modul-saya*')) ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-graduation-cap"></i>
                        <p>
                            Modul Belajar
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        @can('manage-learning-modules')
                        <li class="nav-item">
                            <a href="{{ route('learning-module.index') }}" class="nav-link {{ request()->is('learning-module*') ? 'active' : '' }}">
                                <i class="fas fa-tasks nav-icon"></i>
                                <p>Kelola Modul</p>
                            </a>
                        </li>
                        @endcan
                        @can('view-learning-modules')
                        <li class="nav-item">
                            <a href="{{ route('learning.index') }}" class="nav-link {{ request()->is('modul-saya*') ? 'active' : '' }}">
                                <i class="fas fa-book-reader nav-icon"></i>
                                <p>Modul Saya</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                @canany(['manage-documents', 'view-documents'])
                <li class="nav-item">
                    <a href="/document" class="nav-link {{ ($active ?? '') === 'document' ? 'active' : '' }}">
                        <i class="nav-icon far fa-file-alt"></i>
                        <p>Dokumen</p>
                    </a>
                </li>
                @endcanany

                @canany(['manage-users', 'manage-roles', 'manage-master-data'])
                <li class="nav-header">Pengaturan</li>

                @can('manage-users')
                <li class="nav-item">
                    <a href="/user" class="nav-link {{ request()->is('user*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-users"></i>
                        <p>User</p>
                    </a>
                </li>
                @endcan

                @can('manage-roles')
                <li class="nav-item">
                    <a href="/roles" class="nav-link {{ request()->is('roles*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-user-shield"></i>
                        <p>Jabatan & Hak Akses</p>
                    </a>
                </li>
                @endcan

                @can('manage-master-data')
                <li class="nav-item {{ (request()->is('area*') || request()->is('divisi*') || request()->is('position*') || request()->is('dokumentype*')) ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-database"></i>
                        <p>
                            Master Data
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/area" class="nav-link {{ request()->is('area*') ? 'active' : '' }}">
                                <i class="fas fa-building nav-icon"></i>
                                <p>Area</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/divisi" class="nav-link {{ request()->is('divisi*') ? 'active' : '' }}">
                                <i class="fas fa-sitemap nav-icon"></i>
                                <p>Divisi</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/position" class="nav-link {{ request()->is('position*') ? 'active' : '' }}">
                                <i class="fas fa-briefcase nav-icon"></i>
                                <p>Posisi</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/dokumentype" class="nav-link {{ request()->is('dokumentype*') ? 'active' : '' }}">
                                <i class="fas fa-file-alt nav-icon"></i>
                                <p>Tipe Dokumen</p>
                            </a>
                        </li>
                    </ul>
                </li>
                @endcan
                @endcanany

                @canany(['viewHorizon', 'viewLogViewer'])
                <li class="nav-header">Sistem</li>

                @can('viewHorizon')
                <li class="nav-item">
                    <a href="{{ url(config('horizon.path', 'horizon')) }}" class="nav-link" target="_blank" rel="noopener">
                        <i class="nav-icon fas fa-layer-group"></i>
                        <p>
                            Horizon (Antrian)
                            <i class="right fas fa-external-link-alt"></i>
                        </p>
                    </a>
                </li>
                @endcan

                @can('viewLogViewer')
                <li class="nav-item">
                    <a href="{{ url(config('log-viewer.route_path', 'log-viewer')) }}" class="nav-link" target="_blank" rel="noopener">
                        <i class="nav-icon fas fa-clipboard-list"></i>
                        <p>
                            Log Viewer
                            <i class="right fas fa-external-link-alt"></i>
                        </p>
                    </a>
                </li>
                @endcan
                @endcanany

                <li class="nav-header">Bantuan</li>
                <li class="nav-item">
                    <a href="/panduan" class="nav-link {{ ($active ?? '') === 'panduan' ? 'active' : '' }}">
                        <i class="nav-icon far fa-question-circle"></i>
                        <p>Panduan Penggunaan</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>
@endauth
