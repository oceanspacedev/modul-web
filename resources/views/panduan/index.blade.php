@extends('layout.main_template')

@section('content')
<style>
    .pg-wrap { display: flex; gap: 24px; align-items: flex-start; }
    .pg-toc { position: sticky; top: 76px; width: 250px; flex-shrink: 0; max-height: calc(100vh - 100px); overflow-y: auto; background: #fff; border: 1px solid var(--ring); border-radius: var(--radius-xl); padding: 12px 8px; }
    .pg-toc .toc-title { font-size: .75rem; color: var(--gray-500); font-weight: 500; padding: 6px 10px; }
    .pg-toc a { display: block; padding: 5px 10px; border-radius: var(--radius-lg); color: var(--gray-700); font-size: .875rem; }
    .pg-toc a:hover { background: var(--gray-50); text-decoration: none; color: var(--gray-950); }
    .pg-body { flex: 1; min-width: 0; }
    .pg-card { background: #fff; border: 1px solid var(--ring); border-radius: var(--radius-xl); padding: 24px 28px; margin-bottom: 20px; scroll-margin-top: 76px; }
    .pg-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .pg-card h2 { font-family: var(--font-heading); font-size: 1.125rem; font-weight: 600; color: var(--gray-950); margin: 0; }
    .pg-card h3 { font-family: var(--font-heading); font-size: 1rem; font-weight: 600; color: var(--gray-950); margin: 22px 0 8px; scroll-margin-top: 76px; }
    .pg-card p, .pg-card li { color: var(--gray-700); font-size: .875rem; line-height: 1.65; }
    .pg-card ol, .pg-card ul { padding-left: 20px; }
    .pg-card li { margin-bottom: 4px; }
    .pg-card a:not(.btn) { color: var(--primary-600); }
    .pg-shot { margin: 14px 0; border: 1px solid var(--ring); border-radius: var(--radius-lg); overflow: hidden; background: var(--gray-50); }
    .pg-shot img { width: 100%; display: block; }
    .pg-shot figcaption { font-size: .75rem; color: var(--gray-500); padding: 6px 12px; border-top: 1px solid var(--ring); background: #fff; }
    .pg-note { border: 1px solid var(--ring); background: var(--gray-50); padding: 10px 14px; border-radius: var(--radius-lg); margin: 12px 0; font-size: .875rem; color: var(--gray-700); }
    .pg-warn { border-color: rgba(217, 119, 6, 0.2); background: var(--warning-50); color: var(--warning-700); }
    .pg-card .pg-note li { color: inherit; }
    .pg-more { border: 1px solid var(--ring); border-radius: var(--radius-lg); margin: 12px 0; }
    .pg-more summary { cursor: pointer; padding: 8px 14px; font-size: .875rem; color: var(--gray-700); font-weight: 500; }
    .pg-more > div { padding: 0 14px 6px; border-top: 1px solid var(--ring); }
    .pg-card table { font-size: .875rem; }
    .pg-card code { background: var(--gray-100); padding: 1px 6px; border-radius: 4px; color: var(--gray-800); font-size: .8125rem; }
    @media (max-width: 991px) { .pg-wrap { flex-direction: column; } .pg-toc { position: static; width: 100%; max-height: none; } }
</style>

<div class="content-header">
    <div class="container-fluid">
        <x-page-header title="Panduan Penggunaan" subtitle="Langkah singkat memakai aplikasi Modul. Pilih bagian sesuai kebutuhan Anda." eyebrow="Bantuan" />
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="pg-wrap">

            <nav class="pg-toc" aria-label="Daftar isi">
                <div class="toc-title">Mulai</div>
                <a href="#pengenalan">1. Pengenalan</a>
                <a href="#login">2. Masuk &amp; keluar</a>
                <div class="toc-title mt-2">Admin / Pemateri</div>
                <a href="#dashboard">3. Dashboard</a>
                <a href="#masterdata">4. Master data</a>
                <a href="#user">5. User</a>
                <a href="#role">6. Jabatan &amp; hak akses</a>
                <a href="#pelatihan">7. Pelatihan</a>
                <a href="#soal">8. Soal kuis pelatihan</a>
                <a href="#soal-gambar">&nbsp;&nbsp;&nbsp;Gambar pada soal</a>
                <a href="#zoomai">9. Scan Off Cam (AI)</a>
                <a href="#dokumen">10. Dokumen</a>
                <a href="#video">11. Video materi</a>
                <div class="toc-title mt-2">Peserta</div>
                <a href="#peserta">12. Portal peserta</a>
                <a href="#kuispeserta">13. Mengerjakan kuis</a>
                <div class="toc-title mt-2">Bantuan</div>
                <a href="#faq">14. Masalah umum</a>
                <a href="#akses">15. Hak akses per fitur</a>
                <div class="toc-title mt-2">Modul Belajar</div>
                <a href="#modulbelajar">16. Modul Belajar</a>
                <a href="#video-kuis">Video dengan pretest/posttest</a>
                <a href="#dokumen-kuis">Dokumen dengan pretest/posttest</a>
                <a href="#mode-quizizz">Mode Game (Quizizz)</a>
            </nav>

            <div class="pg-body">

                {{-- 1 --}}
                <div class="pg-card" id="pengenalan">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-info-circle"></i></span><h2>1. Pengenalan</h2></div>
                    <p>Modul dipakai untuk <b>pelatihan internal</b>: jadwal sesi, undangan WhatsApp, presensi, kuis, serta dokumen dan video materi.</p>
                    <ul>
                        <li><b>Admin / Pemateri</b> login ke web dan mengelola fitur sesuai jabatannya.</li>
                        <li><b>Peserta</b> tidak perlu login: cukup buka <b>link pribadi</b> dari WhatsApp.</li>
                    </ul>
                    <h3>Alur singkat</h3>
                    <ol>
                        <li>Admin mengisi master data dan user.</li>
                        <li>Pemateri membuat pelatihan dan memilih peserta. Undangan WA terkirim otomatis.</li>
                        <li>Pemateri menyiapkan soal (manual, impor, atau AI).</li>
                        <li>Saat sesi: buka presensi, lalu buka kuis.</li>
                        <li>Nilai essay, lalu ekspor rekap nilai.</li>
                    </ol>
                    <h3>Menu</h3>
                    <ul>
                        <li><b>Menu</b>: Dashboard, Notifikasi, Video Materi, Pelatihan, Modul Belajar, Dokumen.</li>
                        <li><b>Pengaturan</b>: User, Jabatan &amp; Hak Akses, Master Data.</li>
                        <li><b>Sistem</b> (super admin): Horizon dan Log Viewer.</li>
                    </ul>
                    <div class="pg-note">Menu hanya muncul bila jabatan Anda punya izinnya. Semua dropdown bisa dicari dengan mengetik. Tombol <b>Keluar</b> ada di menu nama Anda (kanan atas).</div>
                    <h3>Import, export &amp; notifikasi</h3>
                    <p>Import dan export (user, rekap pelatihan, rekap modul, soal &amp; kunci jawaban) diproses di belakang layar, jadi Anda tidak perlu menunggu di halaman. Setelah selesai, hasilnya masuk ke menu <b>Notifikasi</b> (ikon lonceng di kanan atas), dan dikirim juga ke WhatsApp serta email bila No. HP / email Anda terisi. File export diunduh dari menu Notifikasi dan tersedia selama 7 hari.</p>
                </div>

                {{-- 2 --}}
                <div class="pg-card" id="login">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-sign-in-alt"></i></span><h2>2. Masuk &amp; keluar</h2></div>
                    <figure class="pg-shot"><img src="/assets/panduan/login.png" alt="Halaman login" loading="lazy"><figcaption>Halaman login.</figcaption></figure>
                    <h3>Dengan password</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/login-password.gif" alt="Animasi: login dengan password" loading="lazy"><figcaption>Login dengan password (data contoh).</figcaption></figure>
                    <ol>
                        <li>Isi <b>Username</b> (atau nomor WhatsApp) dan <b>Kata sandi</b>.</li>
                        <li>Klik <b>Masuk</b>.</li>
                    </ol>
                    <h3>Dengan WhatsApp (OTP)</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/login-whatsapp.gif" alt="Animasi: login via WhatsApp" loading="lazy"><figcaption>Login lewat WhatsApp.</figcaption></figure>
                    <ol>
                        <li>Klik tombol <b>WhatsApp</b>, isi nomor, klik <b>Kirim OTP</b>.</li>
                        <li>Masukkan kode 6 digit dari WhatsApp. Kode berlaku 5 menit.</li>
                    </ol>
                    <p><b>Keluar:</b> klik nama Anda di kanan atas → <b>Keluar</b>.</p>
                </div>

                {{-- 3 --}}
                <div class="pg-card" id="dashboard">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-tachometer-alt"></i></span><h2>3. Dashboard</h2></div>
                    <p>Ringkasan jumlah user, pelatihan, video, peserta, dokumen, dan nilai kuis. Klik kartu untuk membuka halamannya.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/dashboard.png" alt="Dashboard" loading="lazy"><figcaption>Dashboard (data contoh).</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/sidebar-menu.gif" alt="Animasi: membuka menu samping" loading="lazy"><figcaption>Membuka submenu di menu kiri.</figcaption></figure>
                </div>

                {{-- 4 --}}
                <div class="pg-card" id="masterdata">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-database"></i></span><h2>4. Master data</h2></div>
                    <p><b>Pengaturan → Master Data</b> berisi <b>Area</b>, <b>Divisi</b>, <b>Posisi</b>, dan <b>Tipe Dokumen</b>. Isi ini dulu sebelum membuat user atau dokumen.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/divisi.png" alt="Halaman Divisi" loading="lazy"><figcaption>Halaman Divisi.</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/divisi-tambah.gif" alt="Animasi: menambah divisi" loading="lazy"><figcaption>Menambah divisi lewat panel kanan.</figcaption></figure>
                    <ol>
                        <li>Klik tombol <b>Tambah</b> (mis. <b>Tambah Divisi</b>).</li>
                        <li>Isi nama, lalu <b>Simpan</b>.</li>
                    </ol>
                    <div class="pg-note">Nama otomatis jadi HURUF BESAR dan harus unik. Data yang masih dipakai karyawan jangan dihapus.</div>
                </div>

                {{-- 5 --}}
                <div class="pg-card" id="user">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-users"></i></span><h2>5. User</h2></div>
                    <figure class="pg-shot"><img src="/assets/panduan/user-create.gif" alt="Animasi: mengisi form tambah user" loading="lazy"><figcaption>Mengisi form tambah user (data contoh).</figcaption></figure>
                    <h3>Menambah user</h3>
                    <ol>
                        <li><b>Pengaturan → User</b>, klik <b>Tambah User</b>.</li>
                        <li>Isi yang wajib: <b>Nama Lengkap</b>, <b>Area</b>, <b>Divisi</b>, <b>Jabatan</b>, <b>Username</b>, <b>Password</b> (min. 8 karakter). Isi <b>Approval Line</b> (atasan) bila diminta.</li>
                        <li>Isi <b>No. HP</b> agar user bisa login OTP dan menerima undangan WA.</li>
                        <li>Klik <b>Simpan</b>.</li>
                    </ol>
                    <h3>Mengelola</h3>
                    <ul>
                        <li>Cari lewat filter dan kolom pencarian.</li>
                        <li><b>Arsipkan</b> = user tidak bisa login, data tetap ada. <b>Aktifkan</b> di tab Arsip untuk mengembalikan.</li>
                        <li>Centang beberapa user untuk ubah <b>Posisi</b> atau <b>Approval Line</b> sekaligus.</li>
                    </ul>
                    <h3>Impor dari Excel</h3>
                    <ol>
                        <li><b>Import/Export → Download Template</b>, isi filenya.</li>
                        <li><b>Import User (Excel)</b>, pilih file, klik <b>Import</b>.</li>
                    </ol>
                    <p>Import berjalan di belakang layar. Hasilnya dan nomor baris yang gagal muncul di menu <b>Notifikasi</b>; perbaiki lalu impor ulang (tidak membuat duplikat).</p>
                    <details class="pg-more">
                        <summary>Detail untuk super admin: cakupan data, Import JSON, Sinkronisasi DnD</summary>
                        <div>
                            <ul>
                                <li><b>Akses Data</b> (khusus jabatan ADMIN): <b>Semua Badan Usaha</b> = super admin; <b>Badan Usaha Tertentu</b> = hanya area yang dipilih. Minimal harus ada satu super admin.</li>
                                <li>Pengguna non-admin yang boleh kelola user hanya melihat timnya sendiri (bawahan menurut approval line).</li>
                                <li><b>Import JSON (Talenta)</b>: pilih file .json, klik <b>Import JSON</b>. User lama hanya diperbarui email dan No. HP-nya; user baru login lewat OTP. Cek dan lengkapi Area user baru setelahnya.</li>
                                <li id="sync-dnd"><b>Sinkronisasi DnD</b>: mengambil data karyawan dari DnD. Periksa halaman preview, lalu klik <b>Proses Sinkronisasi</b>; hasilnya dikirim ke menu <b>Notifikasi</b>. Juga berjalan otomatis tiap hari.</li>
                            </ul>
                        </div>
                    </details>
                </div>

                {{-- 6 --}}
                <div class="pg-card" id="role">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-user-shield"></i></span><h2>6. Jabatan &amp; hak akses</h2></div>
                    <p>Jabatan menentukan menu yang bisa dibuka. ADMIN punya semua akses; jabatan lain bawaannya hanya bisa melihat (dashboard, video, pelatihan &amp; modul saya, dokumen).</p>
                    <figure class="pg-shot"><img src="/assets/panduan/roles-create.gif" alt="Animasi: membuat jabatan baru" loading="lazy"><figcaption>Membuat jabatan baru dan memilih izinnya.</figcaption></figure>
                    <ul>
                        <li><b>Jabatan baru:</b> klik <b>Tambah Jabatan</b>, isi nama, centang izin, <b>Simpan Jabatan Baru</b>.</li>
                        <li><b>Ubah izin:</b> klik <b>Atur Hak Akses</b> pada jabatan, ubah centang, simpan.</li>
                        <li><b>Ganti jabatan user:</b> tab <b>Penetapan Jabatan Pengguna</b>, pilih jabatan di baris user, klik <b>Simpan</b>.</li>
                    </ul>
                    <div class="pg-note">Centang <b>Trainer</b> pada user agar ia bisa mengelola pelatihan, video, dan dokumen tanpa mengganti jabatannya.</div>
                </div>

                {{-- 7 --}}
                <div class="pg-card" id="pelatihan">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-chalkboard-teacher"></i></span><h2>7. Pelatihan</h2></div>
                    <figure class="pg-shot"><img src="/assets/panduan/training-list.gif" alt="Animasi: memfilter dan mencari pelatihan" loading="lazy"><figcaption>Daftar Jadwal Pelatihan: pindah tab dan mencari.</figcaption></figure>
                    <h3>7.1 Membuat pelatihan</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/training-create.gif" alt="Animasi: mengisi form pelatihan" loading="lazy"><figcaption>Mengisi form pelatihan dan memilih peserta (data contoh).</figcaption></figure>
                    <ol>
                        <li><b>Pelatihan → Jadwal Pelatihan</b>, klik <b>Tambah Pelatihan</b>.</li>
                        <li>Isi <b>Judul</b>, <b>Pemateri</b>, <b>Tanggal</b>, jam, dan <b>Link Meeting</b>.</li>
                        <li>Pilih <b>Dokumen Materi</b> (opsional) dan <b>Mode Kuis</b>: Formal atau Game.</li>
                        <li>Centang peserta (bisa <b>Pilih semua</b> atau saring per divisi). Label <b>Tanpa WA</b> = tidak punya nomor WhatsApp.</li>
                        <li>Klik <b>Simpan Pelatihan</b>. Undangan WA terkirim otomatis.</li>
                    </ol>
                    <h3>7.2 Saat sesi berlangsung</h3>
                    <p>Klik <b>Detail</b> pada pelatihan.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/training-show.png" alt="Detail pelatihan" loading="lazy"><figcaption>Halaman detail pelatihan.</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/training-controls.gif" alt="Animasi: kontrol sesi pelatihan" loading="lazy"><figcaption>Kontrol sesi, lampiran dokumen, dan menu Opsi.</figcaption></figure>
                    <ul>
                        <li><b>Buka Presensi</b> agar peserta bisa absen, lalu <b>Buka Kuis</b> saat kuis dimulai.</li>
                        <li><b>Broadcast WA ke Peserta</b> mengirim ulang undangan ke semua peserta.</li>
                        <li><b>Lampirkan Dokumen</b> dan <b>Upload Video Materi</b> untuk materi sesi.</li>
                        <li>Menu <b>Aksi</b> per peserta: lihat <b>Lembar Jawaban</b>, kirim WA, atur presensi manual, atau <b>Reset Kuis Peserta</b> agar bisa mengulang.</li>
                    </ul>
                    <h3>Setelah sesi</h3>
                    <ul>
                        <li><b>Nilai essay:</b> buka Lembar Jawaban peserta, isi <b>Nilai Essay</b>, klik <b>Simpan Nilai</b>.</li>
                        <li><b>Rekap:</b> <b>Opsi → Export Rekap Nilai</b>, lalu unduh filenya dari menu <b>Notifikasi</b>.</li>
                        <li><b>Edit:</b> <b>Opsi → Edit Informasi Pelatihan</b>. Peserta yang dihapus dari daftar ikut terhapus datanya.</li>
                    </ul>
                </div>

                {{-- 8 --}}
                <div class="pg-card" id="soal">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-question-circle"></i></span><h2>8. Soal kuis pelatihan</h2></div>
                    <p>Dari detail pelatihan klik <b>Soal Kuis</b>. Ada tiga cara menambah soal:</p>
                    <figure class="pg-shot"><img src="/assets/panduan/training-questions.gif" alt="Animasi: menambah soal manual" loading="lazy"><figcaption>Menambah soal manual dengan gambar.</figcaption></figure>
                    <ul>
                        <li><b>Tambah Manual</b>: tulis pertanyaan, isi pilihan, pilih <b>Kunci Jawaban</b>, <b>Simpan Soal</b>.</li>
                        <li><b>Import</b>: <b>Download Template</b>, isi, lalu <b>Import Excel / CSV</b>. Hapus baris contoh di template dulu. Atau <b>Import Teks Cepat</b> untuk menempel soal (klik <b>Isi Contoh</b> untuk melihat formatnya).</li>
                        <li><b>Buat Soal via AI</b>: pilih dokumen materi, atur jumlah soal, klik <b>Mulai Generate Soal</b>. Periksa hasilnya, lalu klik <b>Simpan</b>.</li>
                    </ul>
                    <p><b>Export soal:</b> <b>Import / Export → Export Soal &amp; Kunci Jawaban</b> menyiapkan file semua soal beserta kuncinya di belakang layar (unduh dari menu <b>Notifikasi</b>), dengan format yang sama seperti template, jadi bisa diedit lalu diimpor ke pelatihan lain. Gambar tidak ikut; soal bergambar ditandai di kolom <b>catatan</b>. Soal pretest/posttest modul, video, dan dokumen bisa di-export lewat tombol <b>Export Soal</b> di halaman soalnya.</p>
                    <h3 id="soal-gambar">Gambar pada soal</h3>
                    <ul>
                        <li>Klik <b>Tambah gambar soal</b> atau <b>Gambar A</b>, <b>Gambar B</b>, dst. (JPG/PNG/WEBP, maks. 5 MB).</li>
                        <li>Soal boleh hanya berupa gambar. Gambar tidak bisa diimpor; tambahkan lewat <b>Edit</b>.</li>
                    </ul>
                </div>

                {{-- 9 --}}
                <div class="pg-card" id="zoomai">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-video-slash"></i></span><h2>9. Scan Off Cam (AI)</h2></div>
                    <p>Mencatat peserta yang kameranya mati dari screenshot galeri Zoom.</p>
                    <ol>
                        <li>Di detail pelatihan klik <b>Scan Off Cam</b>.</li>
                        <li>Pilih screenshot (maks. 10), klik <b>Mulai Pindai dengan AI</b>.</li>
                        <li>Periksa nama hasil AI, perbaiki bila salah, lalu klik <b>Konfirmasi &amp; Simpan Catatan Off Cam</b>.</li>
                    </ol>
                    <div class="pg-note">Tanpa AI: klik <b>Input Off Cam Manual</b> di atas tabel peserta, lalu <b>Tandai Off Cam</b> pada peserta.</div>
                </div>

                {{-- 10 --}}
                <div class="pg-card" id="dokumen">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-file-alt"></i></span><h2>10. Dokumen</h2></div>
                    <figure class="pg-shot"><img src="/assets/panduan/document-create.png" alt="Tambah dokumen" loading="lazy"><figcaption>Form Tambah Dokumen.</figcaption></figure>
                    <h3>Menambah dokumen</h3>
                    <ol>
                        <li>Menu <b>Dokumen</b>, klik <b>Tambah Dokumen</b>.</li>
                        <li>Pilih file PDF (maks. 50 MB), <b>Tipe Dokumen</b>, <b>Jabatan</b>, dan <b>Divisi</b>.</li>
                        <li>Klik <b>Simpan</b>.</li>
                    </ol>
                    <h3>Mengelola</h3>
                    <ul>
                        <li><b>Aksi → Edit Dokumen</b> untuk upload versi baru. Versi lama tetap tersimpan di <b>Riwayat Versi</b>.</li>
                        <li><b>Nonaktifkan</b> menyembunyikan dokumen; <b>Hapus</b> memindahkannya ke tab <b>Terhapus</b> (bisa <b>Pulihkan</b>).</li>
                    </ul>
                    <h3 id="dokumen-kuis">Dokumen dengan pretest &amp; posttest</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/dokumen-kuis-buat.gif" alt="Animasi: membuat dokumen dengan pretest dan posttest" loading="lazy"><figcaption>Menyalakan pretest dan posttest saat menambah dokumen.</figcaption></figure>
                    <ol>
                        <li>Di form dokumen, nyalakan <b>Aktifkan Pretest</b> dan/atau <b>Aktifkan Posttest</b>, atur KKM dan Mode Kuis.</li>
                        <li>Klik <b>Simpan</b>, lalu tambahkan soal di halaman yang terbuka. Kuis aktif setelah ada minimal 1 soal.</li>
                    </ol>
                    <p>Peserta mengerjakan pretest dulu, membaca dokumen, lalu posttest.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/dokumen-kuis.gif" alt="Animasi: membuka dokumen ber-kuis" loading="lazy"><figcaption>Tampilan peserta saat membuka dokumen ber-kuis.</figcaption></figure>
                </div>

                {{-- 11 --}}
                <div class="pg-card" id="video">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-play-circle"></i></span><h2>11. Video materi</h2></div>
                    <p>Menu <b>Video Materi</b> berisi galeri video. Klik kartu untuk menonton dan berdiskusi di kolom komentar.</p>
                    <h3>Menambah video</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/video-tambah.gif" alt="Animasi: menambah video dari link" loading="lazy"><figcaption>Menambah video dari link YouTube.</figcaption></figure>
                    <ol>
                        <li>Klik <b>Tambah Video</b>, isi <b>Judul</b>.</li>
                        <li>Pilih <b>Upload File</b> (maks. 2 GB) atau <b>Link Video</b> (YouTube, Google Drive publik, Vimeo).</li>
                        <li>Klik <b>Simpan Video</b>. Jangan tutup halaman selama upload.</li>
                    </ol>
                    <div class="pg-note">Link Google Drive harus diatur ke <b>"Siapa saja yang memiliki link"</b>. Cover boleh dikosongkan, akan dibuat otomatis.</div>
                    <h3 id="video-kuis">Video dengan pretest &amp; posttest</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/video-kuis-buat.gif" alt="Animasi: membuat video dengan pretest dan posttest" loading="lazy"><figcaption>Menyalakan pretest dan posttest saat menambah video.</figcaption></figure>
                    <ol>
                        <li>Di form video, nyalakan <b>Aktifkan Pretest</b> dan/atau <b>Aktifkan Posttest</b>, atur KKM dan Mode Kuis.</li>
                        <li>Klik <b>Simpan Video</b>, lalu tambahkan soal di halaman yang terbuka.</li>
                    </ol>
                    <figure class="pg-shot"><img src="/assets/panduan/kuis-tambah-soal.gif" alt="Animasi: menambah soal sampai kuis aktif" loading="lazy"><figcaption>Setelah soal pertama disimpan, status menjadi <b>Kuis aktif</b>.</figcaption></figure>
                    <p>Peserta wajib login, mengerjakan pretest sebelum menonton, dan posttest setelah video selesai.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/video-pretest.gif" alt="Animasi: pretest sebelum menonton video" loading="lazy"><figcaption>Peserta: pretest dulu, lalu <b>Lanjut Tonton Video</b>.</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/video-posttest.gif" alt="Animasi: posttest setelah video selesai" loading="lazy"><figcaption>Peserta: setelah video selesai, klik <b>Kerjakan Posttest</b>.</figcaption></figure>
                </div>

                {{-- 12 --}}
                <div class="pg-card" id="peserta">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-user-graduate"></i></span><h2>12. Portal peserta</h2></div>
                    <ol>
                        <li>Buka pesan WhatsApp undangan pelatihan, ketuk <b>link pribadi</b> Anda.</li>
                        <li>Setelah pemateri membuka presensi, ketuk <b>Saya Hadir</b> (unggah screenshot Zoom bila diminta).</li>
                        <li>Tidak bisa hadir? Pilih <b>Tidak Hadir</b>, isi alasan, <b>Simpan Keterangan</b>.</li>
                    </ol>
                    <div class="pg-warn pg-note">Jangan bagikan link pribadi Anda ke orang lain.</div>
                </div>

                {{-- 13 --}}
                <div class="pg-card" id="kuispeserta">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-clipboard-check"></i></span><h2>13. Mengerjakan kuis</h2></div>
                    <ol>
                        <li>Setelah kuis dibuka pemateri, ketuk <b>Mulai Kerjakan Kuis</b>.</li>
                        <li>Jawab semua soal, ketuk <b>Kirim Jawaban</b>.</li>
                        <li>Klik <b>Lihat Pembahasan</b> untuk melihat nilai dan kunci jawaban.</li>
                    </ol>
                    <div class="pg-warn pg-note">Kuis hanya <b>satu kali</b>. Pindah tab/aplikasi lebih dari 3 kali membuat kuis terkirim otomatis.</div>
                </div>

                {{-- 14 --}}
                <div class="pg-card" id="faq">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-life-ring"></i></span><h2>14. Masalah umum</h2></div>
                    <table class="table table-sm table-bordered">
                        <tr><th style="width:38%">Masalah</th><th>Solusi</th></tr>
                        <tr><td>Tidak bisa login</td><td>Cek username dan password. Terlalu sering salah? Tunggu 1 menit. Akun diarsipkan? Hubungi admin.</td></tr>
                        <tr><td>OTP tidak masuk</td><td>Pastikan nomor WA di akun benar, tunggu 60 detik lalu kirim ulang.</td></tr>
                        <tr><td>Menu tidak muncul</td><td>Jabatan belum punya izin. Minta admin mengatur di Jabatan &amp; Hak Akses.</td></tr>
                        <tr><td>Peserta tidak bisa absen</td><td>Pemateri belum klik <b>Buka Presensi</b>.</td></tr>
                        <tr><td>Peserta tidak bisa membuka kuis</td><td>Kuis belum dibuka, belum ada soal, atau sudah dikerjakan (pakai <b>Reset Kuis Peserta</b>).</td></tr>
                        <tr><td>WA ke peserta tidak terkirim</td><td>No. HP kosong/salah. Perbaiki di data user, lalu <b>Kirim Notifikasi WA</b>.</td></tr>
                        <tr><td>Video Google Drive tidak bisa diputar</td><td>Ubah akses menjadi "Siapa saja yang memiliki link".</td></tr>
                        <tr><td>Upload gagal / "413 Request Entity Too Large"</td><td>File terlalu besar untuk server. Hubungi administrator.</td></tr>
                        <tr><td>Tombol posttest video belum muncul</td><td>Tonton video sampai selesai.</td></tr>
                        <tr><td>Pretest/posttest tidak muncul</td><td>Kuis belum punya soal. Tambahkan minimal 1 soal.</td></tr>
                        <tr><td>Soal AI gagal dibuat</td><td>API key AI belum diatur atau dokumen tidak terbaca. Hubungi administrator.</td></tr>
                    </table>
                </div>

                {{-- 15 --}}
                <div class="pg-card" id="akses">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-key"></i></span><h2>15. Hak akses per fitur</h2></div>
                    <table class="table table-sm table-bordered">
                        <tr><th>Fitur</th><th class="text-center">ADMIN</th><th class="text-center">Jabatan lain</th><th class="text-center">+ Trainer</th></tr>
                        <tr><td>Dashboard, menonton &amp; komentar video, Pelatihan Saya, Modul Saya, melihat dokumen</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">✔</td></tr>
                        <tr><td>Kelola pelatihan, video, dokumen, Modul Belajar</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">✔</td></tr>
                        <tr><td>User, Jabatan &amp; Hak Akses, Master Data</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">–</td></tr>
                    </table>
                    <p class="small text-muted mb-0">Ini pengaturan bawaan dan bisa diubah per jabatan. Menu Sistem hanya untuk super admin.</p>
                </div>

                {{-- 16 --}}
                <div class="pg-card" id="modulbelajar">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-book-open"></i></span><h2>16. Modul Belajar</h2></div>
                    <p>Belajar mandiri tanpa jadwal: <b>pretest → materi (video/dokumen) → posttest</b>.</p>
                    <h3>Membuat modul</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/modul-buat.gif" alt="Animasi: membuat modul belajar" loading="lazy"><figcaption>Mengisi modul dan menambahkan materi.</figcaption></figure>
                    <ol>
                        <li><b>Modul Belajar → Kelola Modul</b>, klik <b>Tambah Modul</b>.</li>
                        <li>Isi judul, cari dan <b>Tambah</b> video/dokumen ke <b>Materi Modul</b>, isi <b>KKM</b>, pilih sasaran divisi/jabatan.</li>
                        <li>Klik <b>Simpan &amp; Lanjut Buat Soal</b>, tambahkan soal.</li>
                        <li>Klik <b>Publikasikan Modul</b>.</li>
                    </ol>
                    <h3>Mengerjakan modul (peserta)</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/modul-peserta.gif" alt="Animasi: mengerjakan modul belajar" loading="lazy"><figcaption>Membuka modul dari <b>Modul Saya</b> dan mulai pretest.</figcaption></figure>
                    <ol>
                        <li>Buka <b>Modul Belajar → Modul Saya</b>, pilih modul.</li>
                        <li>Kerjakan pretest, selesaikan semua materi, lalu kerjakan posttest.</li>
                    </ol>
                    <p><b>Rekap</b> pada modul menampilkan nilai dan status lulus tiap peserta, bisa di-<b>Export Excel</b> (filenya diunduh dari menu <b>Notifikasi</b>).</p>
                    <h3 id="mode-quizizz">Mode Game (Quizizz)</h3>
                    <p>Pilih <b>Game (Quizizz)</b> di <b>Mode Kuis</b> pada form video, dokumen, atau modul. Satu soal per layar dengan timer; nilai tetap dihitung dari jawaban benar.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/kuis-mode-atur.gif" alt="Animasi: memilih Mode Game (Quizizz)" loading="lazy"><figcaption>Memilih Mode Game dan detik per soal.</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/kuis-mode-quizizz.gif" alt="Animasi: mengerjakan pretest Mode Quizizz" loading="lazy"><figcaption>Tampilan peserta di Mode Game.</figcaption></figure>
                </div>

            </div>
        </div>
    </div>
</section>
@endsection
