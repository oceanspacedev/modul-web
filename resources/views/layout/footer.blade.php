<footer class="main-footer">
    @auth
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
            <span>&copy; {{ date('Y') }} Modul &middot; Magang Wikrama</span>
            <span class="d-none d-sm-inline">Versi 1.0.0</span>
        </div>
    @else
        <div class="container d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
            <span>&copy; {{ date('Y') }} Modul &middot; Magang Wikrama</span>
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
<script>
    // Kotak pencarian (x-search-field) langsung mencari saat pengguna berhenti mengetik, tanpa perlu menekan Enter.
    // Pencarian dijalankan server (halaman dimuat ulang), jadi kursor dikembalikan ke kotaknya supaya bisa lanjut
    // mengetik.
    (function () {
        var FOCUS_KEY = 'search-field-focus';

        document.querySelectorAll('.search-field input[type="search"]').forEach(function (input) {
            var form = input.form;
            if (!form || (form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;

            var submitted = input.value;
            var timer = null;

            function search() {
                clearTimeout(timer);
                if (input.value === submitted) return;
                submitted = input.value;
                try { sessionStorage.setItem(FOCUS_KEY, input.name); } catch (e) {}
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }

            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(search, 500);
            });
            // Enter, dan tombol hapus (x) bawaan kotak pencarian.
            input.addEventListener('search', search);
        });

        // Kotak di dalam tab (mis. tab Penetapan Jabatan) baru terlihat setelah skrip halaman membuka tabnya, jadi
        // ditunggu sebentar sampai kotaknya tampil sebelum kursor dikembalikan.
        var name = null;
        try {
            name = sessionStorage.getItem(FOCUS_KEY);
            sessionStorage.removeItem(FOCUS_KEY);
        } catch (e) {}
        var field = name ? document.querySelector('.search-field input[type="search"][name="' + name + '"]') : null;

        (function refocus(attempt) {
            if (!field) return;
            if (field.offsetParent === null && attempt < 40) {
                setTimeout(function () { refocus(attempt + 1); }, 50);

                return;
            }
            field.focus({ preventScroll: true });
            try { field.setSelectionRange(field.value.length, field.value.length); } catch (e) {}
        })(0);
    })();
</script>
@yield('footer')
</body>

</html>
