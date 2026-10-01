@include('layout.header')

@include('layout.sidebarnav')

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->

        @yield('content')
  </div>

@include('layout.footer')
