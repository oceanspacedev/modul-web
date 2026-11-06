<footer class="main-footer">
    @auth
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
            <span>&copy; {{ date('Y') }} Modul App &middot; Magang Wikrama</span>
            <span class="d-none d-sm-inline">Versi 1.0.0</span>
        </div>
    @else
        <div class="container d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
            <span>&copy; {{ date('Y') }} Modul App &middot; Magang Wikrama</span>
            <span class="d-none d-md-inline">Galeri Publik</span>
        </div>
    @endauth
</footer>

<!-- jQuery -->
<script src="{{ asset('template') }}/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="{{ asset('template') }}/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- overlayScrollbars -->
<script src="{{ asset('template') }}/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<!-- AdminLTE App -->
<script src="{{ asset('template') }}/dist/js/adminlte.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.3/moment.min.js"></script>
<!-- Select2 -->
<script src="{{ asset('template') }}/plugins/select2/js/select2.full.min.js"></script>
<!-- date-range-picker -->
<script src="{{ asset('template') }}/plugins/daterangepicker/daterangepicker.js"></script>
<!-- datepicker -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.2.0/js/bootstrap-datepicker.min.js"></script>
<script src="{{ asset('script.js') }}?v={{ filemtime(public_path('script.js')) }}"></script>
<script>
    // Row dropdowns inside a scrolling table: position the menu fixed (Popper v1)
    // so the table's scroll box doesn't clip it.
    $(document).on('show.bs.dropdown', function (e) {
        if (!$(e.target).closest('.table-responsive').length || !e.relatedTarget) {
            return;
        }
        var dropdown = $(e.relatedTarget).data('bs.dropdown');
        if (dropdown) {
            dropdown._config.boundary = 'window';
            dropdown._config.popperConfig = $.extend({}, dropdown._config.popperConfig, { positionFixed: true });
        }
    });
</script>
@yield('footer')
</body>

</html>
