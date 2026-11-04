{{--
    Cover otomatis: ambil satu frame (detik ke-1) dari video lalu isikan ke input #thumbnail sebagai JPG.
    Dipakai form tambah/edit video. Galeri tidak memutar preview dari file video ber-kuis (alamat file tidak boleh
    tampil sebelum pretest), jadi video tanpa cover akan tampil kosong tanpa ini.
--}}
<script>
window.videoThumbnail = {
    // Cover dipilih manual oleh admin: jangan ditimpa cover otomatis.
    manual: false,

    capture: function (src) {
        return new Promise(function (resolve, reject) {
            var video = document.createElement('video');
            var done = false;
            video.muted = true;
            video.playsInline = true;
            video.preload = 'auto';

            function draw() {
                if (done) return;
                done = true;
                try {
                    var scale = Math.min(1, 1280 / (video.videoWidth || 1280));
                    var canvas = document.createElement('canvas');
                    canvas.width = Math.round((video.videoWidth || 1280) * scale);
                    canvas.height = Math.round((video.videoHeight || 720) * scale);
                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob(function (blob) {
                        video.removeAttribute('src');
                        video.load();
                        blob ? resolve(new File([blob], 'cover-otomatis.jpg', { type: 'image/jpeg' })) : reject(new Error('blob'));
                    }, 'image/jpeg', 0.85);
                } catch (e) {
                    reject(e);
                }
            }

            video.addEventListener('loadeddata', function () {
                video.currentTime = Math.min(1, (video.duration || 2) / 2);
                // Sumber yang tidak bisa di-seek: pakai frame yang sudah ada.
                setTimeout(draw, 8000);
            });
            video.addEventListener('seeked', draw);
            video.addEventListener('error', function () { reject(new Error('load')); });
            video.src = src;
        });
    },

    apply: function (file) {
        var input = document.getElementById('thumbnail');
        var transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;

        document.getElementById('thumbnail_label').innerText = 'cover-otomatis.jpg (diambil dari video)';
        var preview = document.getElementById('thumbnailPreview');
        preview.src = URL.createObjectURL(file);
        document.getElementById('thumbnailPreviewContainer').style.display = 'block';
    },

    // Isi cover dari file video yang baru dipilih, kecuali admin sudah memilih cover sendiri.
    fromSelectedFile: function (file) {
        if (this.manual || !file) return;
        var self = this;
        this.capture(URL.createObjectURL(file)).then(function (cover) {
            if (!self.manual) self.apply(cover);
        }).catch(function () {
            // Format yang tidak bisa diputar browser (mis. MKV): admin bisa memilih cover manual.
        });
    }
};
</script>
