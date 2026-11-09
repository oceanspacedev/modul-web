{{--
    Dipasang sekali di halaman kelola soal (pelatihan & modul).
    Kotak "Tambah gambar" untuk kartu review soal AI. Pilih file -> langsung diunggah ke $uploadUrl,
    path hasilnya disimpan di data-image pada .quiz-img-slot dan dibaca lagi saat soal disimpan.
    Pemakaian di JS: quizImageSlot('Gambar soal') menghasilkan HTML slot; quizImageSlotValue(el) membaca path-nya.
--}}
<script>
(function () {
    var uploadUrl = @json($uploadUrl);
    var csrf = @json(csrf_token());

    function escapeAttr(text) {
        return String(text || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    window.quizImageSlot = function (label) {
        return '<div class="quiz-img-slot" data-image="">' +
            '<div class="quiz-img-preview d-none">' +
                '<img src="" alt="" class="quiz-img quiz-img-thumb">' +
                '<button type="button" class="quiz-img-remove" title="Hapus gambar" aria-label="Hapus gambar">&times;</button>' +
            '</div>' +
            '<label class="quiz-img-add mb-0">' +
                '<i class="fas fa-image mr-1"></i><span class="quiz-img-label">' + escapeAttr(label || 'Tambah gambar') + '</span>' +
                '<input type="file" accept="image/jpeg,image/png,image/webp" class="d-none">' +
            '</label>' +
            '<div class="quiz-img-error small text-danger d-none"></div>' +
        '</div>';
    };

    window.quizImageSlotValue = function (slot) {
        return slot ? (slot.getAttribute('data-image') || null) : null;
    };

    function setBusy(slot, busy) {
        slot.classList.toggle('is-uploading', busy);
        slot.querySelector('.quiz-img-label').textContent = busy ? 'Mengunggah...' : (slot.getAttribute('data-image') ? 'Ganti gambar' : 'Tambah gambar');
    }

    function showError(slot, message) {
        var el = slot.querySelector('.quiz-img-error');
        el.textContent = message || '';
        el.classList.toggle('d-none', !message);
    }

    // Form tambah/edit manual (x-quiz-image-field): tampilkan nama file yang dipilih.
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches('.quiz-img-field .quiz-img-input')) {
            return;
        }
        var name = input.closest('.quiz-img-field').querySelector('.quiz-img-filename');
        name.textContent = input.files.length ? input.files[0].name : '';
    });

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches('.quiz-img-slot input[type="file"]') || !input.files.length) {
            return;
        }
        var slot = input.closest('.quiz-img-slot');
        var form = new FormData();
        form.append('image', input.files[0]);
        showError(slot, '');
        setBusy(slot, true);

        fetch(uploadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: form
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        }).then(function (result) {
            if (!result.ok || !result.data.path) {
                var errors = result.data && result.data.errors ? Object.values(result.data.errors)[0][0] : null;
                throw new Error(errors || (result.data && result.data.message) || 'Gagal mengunggah gambar.');
            }
            slot.setAttribute('data-image', result.data.path);
            slot.querySelector('.quiz-img-preview img').src = result.data.url;
            slot.querySelector('.quiz-img-preview').classList.remove('d-none');
        }).catch(function (err) {
            showError(slot, err.message);
        }).finally(function () {
            input.value = '';
            setBusy(slot, false);
        });
    });

    document.addEventListener('click', function (e) {
        var button = e.target.closest('.quiz-img-slot .quiz-img-remove');
        if (!button) {
            return;
        }
        var slot = button.closest('.quiz-img-slot');
        slot.setAttribute('data-image', '');
        slot.querySelector('.quiz-img-preview').classList.add('d-none');
        slot.querySelector('.quiz-img-preview img').src = '';
        setBusy(slot, false);
    });
})();
</script>
