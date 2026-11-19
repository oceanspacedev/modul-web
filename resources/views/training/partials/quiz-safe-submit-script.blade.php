{{--
    Halaman kuis formal tidak kehilangan jawaban peserta:
    - Jawaban disimpan di perangkat peserta setiap kali diisi, dan dikembalikan bila halaman dimuat ulang.
    - Kiriman otomatis (waktu habis, batas pindah tab) memakai jeda acak supaya ratusan peserta tidak mengirim pada
      detik yang sama, lalu dicoba lagi bila server sibuk atau koneksi putus. Server mengenali kiriman ulang dari nomor
      percobaannya, jadi mengulang tidak memakai jatah percobaan.
    Butuh form #formQuiz. $phase, $participant, $attemptNumber.
--}}
<script>
(function () {
    const form = document.getElementById('formQuiz');
    if (!form) return;

    const PREFIX = @json('quiz:'.$participant->token.':'.$phase.':');
    const KEY = PREFIX + @json((string) $attemptNumber);

    function storage() {
        try { return window.localStorage; } catch (e) { return null; }
    }

    function save() {
        const store = storage();
        if (!store) return;
        const answers = {};
        new FormData(form).forEach(function (value, name) {
            if (name.indexOf('answers[') === 0) answers[name] = value;
        });
        try { store.setItem(KEY, JSON.stringify(answers)); } catch (e) { /* penyimpanan penuh/diblokir */ }
    }

    function restore() {
        const store = storage();
        if (!store) return;
        try {
            // Jawaban percobaan lain untuk kuis ini sudah tidak dipakai.
            Object.keys(store).forEach(function (key) {
                if (key.indexOf(PREFIX) === 0 && key !== KEY) store.removeItem(key);
            });
            const answers = JSON.parse(store.getItem(KEY) || '{}');
            Object.keys(answers).forEach(function (name) {
                form.querySelectorAll('[name="' + CSS.escape(name) + '"]').forEach(function (field) {
                    if (field.type === 'radio' || field.type === 'checkbox') {
                        field.checked = field.value === answers[name];
                    } else {
                        field.value = answers[name];
                    }
                });
            });
        } catch (e) { /* data rusak: abaikan */ }
    }

    restore();
    form.addEventListener('change', save);
    form.addEventListener('input', save);

    function showStatus(html) {
        let box = document.getElementById('quizSendStatus');
        if (!box) {
            box = document.createElement('div');
            box.id = 'quizSendStatus';
            box.setAttribute('role', 'status');
            box.style.cssText = 'position:fixed;inset:0;z-index:2000;background:rgba(255,255,255,.95);display:flex;align-items:center;justify-content:center;padding:24px;text-align:center;';
            document.body.appendChild(box);
        }
        box.innerHTML = '<div style="max-width:360px">' + html + '</div>';
    }

    const RETRY_DELAYS = [2000, 4000, 8000, 15000, 30000];

    // Kirim otomatis dengan aman. spreadMs: jeda acak maksimum sebelum kiriman pertama.
    window.sendQuizSafely = function (spreadMs) {
        save();
        showStatus('<div class="spinner-border text-primary mb-3" role="status"></div><p class="mb-0 font-weight-bold">Menyimpan jawaban Anda...</p><p class="text-muted small">Jangan tutup halaman ini.</p>');

        function attempt(n) {
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (response) {
                // 403 = jatah percobaan sudah habis: hasil sebelumnya yang dipakai.
                if (!response.ok && response.status !== 403) throw new Error('HTTP ' + response.status);
                return response.json();
            }).then(function (data) {
                const store = storage();
                if (store) { try { store.removeItem(KEY); } catch (e) {} }
                window.location.href = (data && data.redirect_url) || @json($phaseUrls['result']);
            }).catch(function () {
                if (n < RETRY_DELAYS.length) {
                    showStatus('<div class="spinner-border text-warning mb-3" role="status"></div><p class="mb-0 font-weight-bold">Server sedang sibuk, mencoba menyimpan lagi...</p><p class="text-muted small">Jawaban Anda aman di perangkat ini. Jangan tutup halaman.</p>');
                    setTimeout(function () { attempt(n + 1); }, RETRY_DELAYS[n] + Math.random() * 2000);
                    return;
                }
                showStatus('<p class="font-weight-bold text-danger">Jawaban belum tersimpan.</p><p class="text-muted small">Periksa koneksi internet Anda, lalu tekan tombol di bawah. Jawaban Anda masih tersimpan di perangkat ini.</p><button type="button" class="btn btn-primary" id="btnQuizResend">Kirim Ulang</button>');
                document.getElementById('btnQuizResend').onclick = function () {
                    showStatus('<div class="spinner-border text-primary mb-3" role="status"></div><p class="mb-0 font-weight-bold">Menyimpan jawaban Anda...</p>');
                    attempt(RETRY_DELAYS.length);
                };
            });
        }

        setTimeout(function () { attempt(0); }, Math.random() * (spreadMs || 0));
    };
})();
</script>
