<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MODUL | APP</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    {{-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous"> --}}
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
    <!-- Modern UI Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/modern-ui.css') }}?v={{ filemtime(public_path('css/modern-ui.css')) }}">
    <style>
        /* We are stopping user from
        printing our webpage */
        @media print {

            html,
            body {

                /* Hide the whole page */
                display: none;
            }
        }
    </style>
</head>

<body class="hold-transition @auth sidebar-mini layout-fixed @else layout-top-nav @endauth">

    <!-- Site wrapper -->
    <div class="wrapper">
        @auth
        <!-- Navbar for Authenticated Users -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i
                            class="fas fa-bars"></i></a>
                </li>
            </ul>

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto align-items-center">
                @can('view-dashboard')
                <li class="nav-item mr-2">
                    <a href="/dashboard" class="nav-link"><i class="fas fa-tachometer-alt mr-1"></i> Dashboard</a>
                </li>
                @endcan
                <li class="nav-item">
                    <form action="/logout" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm px-3" style="border-radius: 6px;">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </button>
                    </form>
                </li>
            </ul>
        </nav>
        <!-- /.navbar -->
        @else
        <!-- Navbar for Public Visitors (No Sidebar) -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom shadow-sm">
            <div class="container">
                <a href="/video" class="navbar-brand d-flex align-items-center">
                    <span class="d-inline-flex align-items-center justify-content-center bg-primary rounded-circle mr-2" style="width: 32px; height: 32px;">
                        <i class="fas fa-layer-group text-white" style="font-size: 0.9rem;"></i>
                    </span>
                    <span class="brand-text font-weight-bold text-dark" style="letter-spacing: -0.01em;">MODUL <span class="badge badge-primary font-weight-normal ml-1">APP</span></span>
                </a>

                <ul class="navbar-nav ml-auto align-items-center">
                    <li class="nav-item mr-3 d-none d-sm-block">
                        <a href="/video" class="nav-link font-weight-bold {{ ($active ?? '') === 'video' ? 'text-primary' : 'text-muted' }}">
                            <i class="fas fa-play-circle mr-1"></i> Galeri Video
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('login') }}" class="btn btn-primary btn-sm px-3 shadow-sm font-weight-bold">
                            <i class="fas fa-sign-in-alt mr-1"></i> Masuk
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        <!-- /.navbar -->
        @endauth
