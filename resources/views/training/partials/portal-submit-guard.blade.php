{{--
    Form portal peserta hanya terkirim sekali: klik kedua (koneksi lambat, tombol ditekan berulang) diabaikan dan
    tombolnya menampilkan "Mengirim...". Halaman yang dibuka lagi lewat tombol kembali mendapat tombolnya kembali.
--}}
<script>
(function () {
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        // Pemeriksaan lain (mis. konfirmasi) bisa membatalkan kiriman; tandai hanya yang benar-benar terkirim.
        setTimeout(function () {
            if (event.defaultPrevented) return;
            form.dataset.submitting = '1';
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.dataset.label = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" role="status"></span> Mengirim...';
            });
        }, 0);
    });

    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) return;
        document.querySelectorAll('form[data-submitting="1"]').forEach(function (form) {
            delete form.dataset.submitting;
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = false;
                if (button.dataset.label) button.innerHTML = button.dataset.label;
            });
        });
    });
})();
</script>
