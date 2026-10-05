<footer class="main-footer">
    @auth
        <div class="float-right d-none d-sm-block">
            <b>Version</b> 1.0.0
        </div>
        <strong>Copyright &copy; 2026 <a href="#">MAGANG WIKRAMA</a>.</strong> All rights reserved.
    @else
        <div class="container text-center text-md-left d-md-flex justify-content-between align-items-center">
            <div>
                <strong>Copyright &copy; 2026 <a href="#">MAGANG WIKRAMA</a>.</strong> All rights reserved.
            </div>
            <div class="d-none d-md-block text-muted small">
                Modul App &bull; Galeri Publik
            </div>
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
<!-- AdminLTE for demo purposes -->
<script src="{{ asset('template') }}/dist/js/demo.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.3/moment.min.js"></script>
<!-- Select2 -->
<script src="{{ asset('template') }}/plugins/select2/js/select2.full.min.js"></script>
<!-- date-range-picker -->
<script src="{{ asset('template') }}/plugins/daterangepicker/daterangepicker.js"></script>
<!-- datepicker -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.2.0/js/bootstrap-datepicker.min.js"></script>
<script src="{{ asset('script.js') }}?v={{ filemtime(public_path('script.js')) }}"></script>
{{-- <script>
    $(document).on("click", ".passingID", function () {
     var ids = $(this).data('id');
     $(".modal-body #resultQuiz").val( ids );
    });
</script> --}}
@yield('footer')
</body>

</html>
