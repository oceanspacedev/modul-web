<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MODUL | APP</title>

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
                    <a class="nav-link topbar-toggle" data-widget="pushmenu" href="#" role="button" aria-label="Buka/tutup menu">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ml-auto align-items-center">
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link user-menu-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="avatar">{{ $initials ?: 'U' }}</span>
                        <span class="user-menu-name d-none d-sm-inline">{{ $authUser->nama_lengkap ?? $authUser->username }}</span>
                        <i class="fas fa-chevron-down"></i>
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
                            @can('view-dashboard')
                            <a href="/dashboard" class="dropdown-item"><i class="fas fa-th-large"></i> Dashboard</a>
                            @endcan
                            <a href="/panduan" class="dropdown-item"><i class="fas fa-book-open"></i> Panduan Penggunaan</a>
                            <div class="dropdown-divider"></div>
                            <form action="/logout" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt text-danger"></i> Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </li>
            </ul>
        </nav>
        @else
        <nav class="main-header navbar navbar-expand navbar-light">
            <div class="container">
                <a href="/video" class="navbar-brand d-flex align-items-center" style="gap: 10px;">
                    <span class="brand-logo"><i class="fas fa-layer-group"></i></span>
                    <span class="text-heading font-weight-bold text-dark" style="font-size: 0.9375rem;">Modul App</span>
                </a>

                <ul class="navbar-nav ml-auto align-items-center" style="gap: 8px;">
                    <li class="nav-item d-none d-sm-block">
                        <a href="/video" class="nav-link {{ ($active ?? '') === 'video' ? 'text-dark' : '' }}">
                            Galeri Video
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('login') }}" class="btn btn-primary btn-sm">
                            Masuk
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        @endauth
