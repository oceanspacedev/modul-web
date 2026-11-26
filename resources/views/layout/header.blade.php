<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) && is_string($title) && $title !== '' ? $title.' · Modul' : 'Modul' }}</title>
    @include('layout.partials.favicon')
    @include('layout.partials.theme')

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('template') }}/plugins/fontawesome-free/css/all.min.css">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="{{ asset('template') }}/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('template') }}/dist/css/adminlte.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('template') }}/plugins/select2/css/select2.min.css">
    <!-- daterange picker -->
    <link rel="stylesheet" href="{{ asset('template') }}/plugins/daterangepicker/daterangepicker.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.2.0/css/datepicker.min.css" rel="stylesheet">
    <!-- App design system (fonts, tokens, component overrides) -->
    @vite('resources/css/app.css')
</head>

<body class="hold-transition @auth sidebar-mini layout-fixed @else layout-top-nav @endauth">
    @auth
    <script>
        // Sidebar yang diciutkan tetap ciut setelah pindah halaman, dan di layar sempit (< 992px) langsung
        // tersembunyi sebelum halaman digambar supaya tidak sempat berkedip menutupi konten.
        try {
            if (window.matchMedia('(min-width: 992px)').matches) {
                if (localStorage.getItem('sidebar-collapsed') === '1') {
                    document.body.classList.add('sidebar-collapse');
                }
            } else {
                document.body.classList.add('sidebar-closed', 'sidebar-collapse');
            }
        } catch (e) {}
    </script>
    @endauth

    <!-- Site wrapper -->
    <div class="wrapper">
        @auth
        @php
            $authUser = auth()->user();
            $initials = collect(preg_split('/\s+/', trim($authUser->nama_lengkap ?? $authUser->username ?? '')))
                ->filter()
                ->take(2)
                ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
                ->implode('');
        @endphp
        <nav class="main-header navbar navbar-expand navbar-light">
            <ul class="navbar-nav align-items-center">
                <li class="nav-item">
                    <a class="nav-link topbar-toggle" data-widget="pushmenu" data-auto-collapse-size="0" href="#" role="button" aria-label="Buka/tutup menu">
                        <i class="fas fa-bars" aria-hidden="true"></i>
                    </a>
                </li>
                <li class="nav-item d-lg-none">
                    <a href="{{ $authUser->homeUrl() }}" class="topbar-brand">
                        <img src="{{ asset('images/logo.svg') }}" alt="" class="brand-logo">
                        <span class="topbar-brand-text">Modul</span>
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ml-auto align-items-center topbar-actions">
                @impersonating
                <li class="nav-item">
                    <form action="{{ route('impersonate.leave') }}" method="POST" class="m-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-warning btn-sm topbar-impersonate-btn" title="Kembali ke akun saya" aria-label="Kembali ke akun saya">
                            <i class="fas fa-user-secret" aria-hidden="true"></i><span class="d-none d-md-inline"> Kembali ke akun saya</span>
                        </button>
                    </form>
                </li>
                @endImpersonating
                <li class="nav-item">
                    @include('layout.partials.theme-toggle', ['class' => 'nav-link topbar-toggle'])
                </li>
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link topbar-toggle notification-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Notifikasi">
                        <i class="far fa-bell" aria-hidden="true"></i>
                        <span class="notification-badge" data-notification-count @if ($unreadNotificationCount === 0) hidden @endif>{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right notification-menu">
                        <div class="notification-menu-header">
                            <span>Notifikasi</span>
                            @if ($unreadNotificationCount > 0)
                                <form action="{{ route('notifications.read-all') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm p-0">Tandai semua dibaca</button>
                                </form>
                            @endif
                        </div>
                        <div class="notification-menu-list">
                            @forelse ($latestNotifications as $notification)
                                <a href="{{ route('notifications.index') }}" class="dropdown-item notification-menu-item {{ $notification->read_at ? '' : 'is-unread' }}">
                                    <span class="notification-menu-title text-truncate">{{ $notification->data['title'] ?? 'Notifikasi' }}</span>
                                    <span class="notification-menu-meta">{{ $notification->created_at->diffForHumans() }}</span>
                                </a>
                            @empty
                                <div class="notification-menu-empty">Belum ada notifikasi.</div>
                            @endforelse
                        </div>
                        <a href="{{ route('notifications.index') }}" class="dropdown-item notification-menu-footer">Lihat semua notifikasi</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link user-menu-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="avatar">{{ $initials ?: 'U' }}</span>
                        <span class="user-menu-name d-none d-sm-inline">{{ $authUser->nama_lengkap ?? $authUser->username }}</span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right user-menu">
                        <div class="user-menu-header">
                            <span class="avatar avatar-lg">{{ $initials ?: 'U' }}</span>
                            <div class="min-w-0">
                                <div class="name text-truncate">{{ $authUser->nama_lengkap ?? $authUser->username }}</div>
                                <div class="meta text-truncate">{{ $authUser->jabatan?->name ?? $authUser->username }}</div>
                            </div>
                        </div>
                        <div class="user-menu-body">
                            @can('open-dashboard')
                            <a href="/dashboard" class="dropdown-item"><i class="fas fa-th-large"></i> Dashboard</a>
                            @endcan
                            <a href="/panduan" class="dropdown-item"><i class="fas fa-book-open"></i> Panduan Penggunaan</a>
                            <div class="dropdown-divider"></div>
                            @impersonating
                            <form action="{{ route('impersonate.leave') }}" method="POST" class="m-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="dropdown-item text-warning">
                                    <i class="fas fa-user-secret text-warning"></i> Kembali ke akun saya
                                </button>
                            </form>
                            @else
                            <form action="/logout" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt text-danger"></i> Keluar
                                </button>
                            </form>
                            @endImpersonating
                        </div>
                    </div>
                </li>
            </ul>
        </nav>
        @else
        <nav class="main-header navbar navbar-expand navbar-light public-header">
            <div class="container public-header-inner">
                <a href="/video" class="navbar-brand public-brand">
                    <img src="{{ asset('images/logo.svg') }}" alt="" class="brand-logo">
                    <span class="topbar-brand-text">Modul</span>
                </a>

                <ul class="navbar-nav public-nav">
                    <li class="nav-item">
                        <a href="/video" class="nav-link {{ ($active ?? '') === 'video' ? 'is-active' : '' }}" @if(($active ?? '') === 'video') aria-current="page" @endif>
                            Galeri Video
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('document-request.create') }}" class="nav-link {{ ($active ?? '') === 'document-request' ? 'is-active' : '' }}" @if(($active ?? '') === 'document-request') aria-current="page" @endif>
                            Permintaan Dokumen
                        </a>
                    </li>
                </ul>

                <div class="public-actions">
                    @include('layout.partials.theme-toggle', ['class' => 'nav-link topbar-toggle'])
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">
                        Masuk
                    </a>
                </div>
            </div>
        </nav>
        @endauth
