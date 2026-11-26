{{--
    Tema terang/gelap. Disertakan di <head> SEBELUM stylesheet supaya atribut data-theme sudah terpasang saat halaman
    pertama kali digambar (tanpa kedip terang → gelap). Pilihan pengguna disimpan di localStorage ("modul-theme");
    selama belum memilih, tema mengikuti pengaturan sistem. Tombolnya: layout.partials.theme-toggle.
    Halaman lain bisa mendengarkan event "themechange" di document (mis. grafik yang perlu digambar ulang).
--}}
<meta name="color-scheme" content="light dark">
<script>
    (function () {
        var KEY = 'modul-theme';
        var root = document.documentElement;
        var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

        function stored() {
            try {
                var value = localStorage.getItem(KEY);
                return value === 'dark' || value === 'light' ? value : null;
            } catch (e) {
                return null;
            }
        }

        function resolve() {
            return stored() || (media && media.matches ? 'dark' : 'light');
        }

        function syncToggles(theme) {
            var buttons = document.querySelectorAll('[data-theme-toggle]');
            for (var i = 0; i < buttons.length; i++) {
                var label = theme === 'dark' ? 'Ganti ke mode terang' : 'Ganti ke mode gelap';
                buttons[i].setAttribute('aria-label', label);
                buttons[i].setAttribute('title', label);
            }
        }

        function apply(theme) {
            var changed = root.getAttribute('data-theme') !== theme;
            // color-scheme sengaja tidak dipasang inline: stylesheet yang menurunkannya dari data-theme, supaya aturan
            // cetak (selalu terang) tetap menang.
            root.setAttribute('data-theme', theme);
            syncToggles(theme);
            if (changed) {
                document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: theme } }));
            }
        }

        function set(theme) {
            try {
                localStorage.setItem(KEY, theme);
            } catch (e) {}
            apply(theme);
        }

        apply(resolve());

        window.ModulTheme = {
            get: function () { return root.getAttribute('data-theme') || 'light'; },
            set: set,
            toggle: function () { set(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); },
        };

        // Belum memilih sendiri: ikuti perubahan tema sistem.
        if (media) {
            var onSystemChange = function () { if (!stored()) apply(resolve()); };
            if (media.addEventListener) media.addEventListener('change', onSystemChange);
            else if (media.addListener) media.addListener(onSystemChange);
        }

        // Tab lain mengganti tema: ikut berubah.
        window.addEventListener('storage', function (event) {
            if (event.key === KEY) apply(resolve());
        });

        // Halaman yang dipulihkan dari cache maju/mundur tidak menjalankan skrip ini lagi: samakan dengan pilihan terbaru.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) apply(resolve());
        });

        document.addEventListener('DOMContentLoaded', function () { syncToggles(root.getAttribute('data-theme')); });
        document.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
            if (!button) return;
            event.preventDefault();
            window.ModulTheme.toggle();
        });
    })();
</script>
