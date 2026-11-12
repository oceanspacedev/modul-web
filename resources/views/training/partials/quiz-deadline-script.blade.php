{{--
    Batas waktu di halaman pengerjaan kuis pelatihan (formal dan game): hitung mundur sampai fase ditutup otomatis,
    lalu memanggil onTimeUp supaya jawaban dikumpulkan. Jam tutup diselaraskan berkala dengan server, jadi waktu
    tambahan dari pemateri sampai ke peserta yang sedang mengerjakan. Sisa waktu memakai detik dari server, bukan jam
    perangkat peserta.
    Butuh elemen #deadlineBadge (tersembunyi) berisi #deadlineClock.
    $phase, $participant, $secondsLeft (null = tanpa batas waktu).
--}}
<script>
window.startQuizDeadline = function (onTimeUp) {
    const STATUS_URL = @json('/training/portal/'.$participant->token.'/status');
    const KEY = @json($phase === 'pretest' ? 'pretest' : 'quiz');
    const badge = document.getElementById('deadlineBadge');
    const clock = document.getElementById('deadlineClock');
    let endsAt = null;
    let finished = false;
    let checking = false;

    function setSecondsLeft(secondsLeft) {
        endsAt = secondsLeft === null || secondsLeft === undefined ? null : Date.now() + Number(secondsLeft) * 1000;
        badge.hidden = endsAt === null;
        tick();
    }

    function fetchStatus() {
        return fetch(STATUS_URL, { headers: { 'Accept': 'application/json' } }).then(function (res) { return res.json(); });
    }

    function timeUp() {
        if (finished) return;
        finished = true;
        onTimeUp();
    }

    function tick() {
        if (finished || checking || endsAt === null) return;

        const left = Math.max(0, Math.round((endsAt - Date.now()) / 1000));
        clock.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
        badge.classList.toggle('badge-danger', left <= 60);
        badge.classList.toggle('badge-warning', left > 60);
        if (left > 0) return;

        // Sebelum mengumpulkan, pastikan pemateri tidak baru saja menambah waktu.
        checking = true;
        fetchStatus()
            .then(function (data) {
                checking = false;
                if (data['is_' + KEY + '_active'] && data[KEY + '_seconds_left'] > 0) {
                    setSecondsLeft(data[KEY + '_seconds_left']);
                } else {
                    timeUp();
                }
            })
            .catch(function () {
                checking = false;
                timeUp();
            });
    }

    // Fase yang dikunci manual tidak mengubah apa pun: peserta yang sedang mengerjakan tetap boleh menyelesaikan.
    function sync() {
        if (finished || checking) return;
        fetchStatus()
            .then(function (data) {
                if (!finished && !checking && data['is_' + KEY + '_active']) setSecondsLeft(data[KEY + '_seconds_left']);
            })
            .catch(function () { /* coba lagi di pengecekan berikutnya */ });
    }

    setSecondsLeft(@json($secondsLeft));
    setInterval(tick, 1000);
    setInterval(sync, 15000);

    return { stop: function () { finished = true; } };
};
</script>
