@extends('layout.main_template')

@section('content')
<style>
    .pg-wrap { display: flex; gap: 24px; align-items: flex-start; }
    .pg-toc { position: sticky; top: 76px; width: 250px; flex-shrink: 0; max-height: calc(100vh - 100px); overflow-y: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 8px; }
    .pg-toc .toc-title { font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; font-weight: 700; padding: 6px 12px; }
    .pg-toc a { display: block; padding: 6px 12px; border-radius: 8px; color: #334155; font-size: .86rem; }
    .pg-toc a:hover { background: #f1f5f9; text-decoration: none; color: #1d4ed8; }
    .pg-body { flex: 1; min-width: 0; }
    .pg-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px 28px; margin-bottom: 20px; }
    .pg-card h2 { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
    .pg-card h3 { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 22px 0 8px; }
    .pg-card p, .pg-card li { color: #334155; font-size: .93rem; line-height: 1.65; }
    .pg-card ol, .pg-card ul { padding-left: 20px; }
    .pg-card li { margin-bottom: 4px; }
    .pg-shot { margin: 14px 0; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #f8fafc; }
    .pg-shot img { width: 100%; display: block; }
    .pg-shot figcaption { font-size: .78rem; color: #64748b; padding: 6px 12px; border-top: 1px solid #e2e8f0; background: #fff; }
    .pg-note { border-left: 4px solid #3b82f6; background: #eff6ff; padding: 10px 14px; border-radius: 6px; margin: 12px 0; font-size: .88rem; color: #1e3a8a; }
    .pg-warn { border-left-color: #f59e0b; background: #fffbeb; color: #78350f; }
    .pg-card table { font-size: .86rem; }
    .pg-card code { background: #f1f5f9; padding: 1px 6px; border-radius: 4px; color: #be123c; }
    .pg-tabs .nav-link { font-weight: 600; }
    @media (max-width: 991px) { .pg-wrap { flex-direction: column; } .pg-toc { position: static; width: 100%; max-height: none; } }
</style>

<div class="content-header">
    <div class="container-fluid">
        <h1 class="m-0 font-weight-bold" style="font-size: 1.5rem; color: #0f172a; letter-spacing: -0.02em;">Panduan Penggunaan</h1>
        <p class="text-muted small mt-1 mb-0">Cara memakai sistem Modul App dari awal sampai akhir, untuk Admin/Pemateri dan Peserta.</p>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="pg-wrap">

            <nav class="pg-toc" aria-label="Daftar isi">
                <div class="toc-title">Mulai</div>
                <a href="#pengenalan">1. Pengenalan sistem</a>
                <a href="#login">2. Masuk &amp; keluar</a>
                <div class="toc-title mt-2">Panduan Admin / Pemateri</div>
                <a href="#dashboard">3. Dashboard</a>
                <a href="#masterdata">4. Data master</a>
                <a href="#user">5. Pengguna</a>
                <a href="#role">6. Role &amp; hak akses</a>
                <a href="#pelatihan">7. Pelatihan</a>
                <a href="#soal">8. Soal kuis pelatihan</a>
                <a href="#zoomai">9. Scan Off Cam Zoom (AI)</a>
                <a href="#dokumen">10. Dokumen</a>
                <a href="#video">11. Video materi</a>
                <div class="toc-title mt-2">Panduan Peserta</div>
                <a href="#peserta">12. Portal peserta</a>
                <a href="#kuispeserta">13. Mengerjakan kuis</a>
                <div class="toc-title mt-2">Bantuan</div>
                <a href="#faq">14. FAQ &amp; masalah umum</a>
                <a href="#akses">15. Hak akses per fitur</a>
            </nav>

            <div class="pg-body">

                {{-- 1 --}}
                <div class="pg-card" id="pengenalan">
                    <h2>1. Pengenalan sistem</h2>
                    <p>Modul App adalah aplikasi untuk mengelola <b>pelatihan internal</b>: menjadwalkan sesi, mengundang peserta lewat WhatsApp, mencatat kehadiran, memberi kuis evaluasi, serta menyimpan dokumen dan video materi.</p>
                    <h3>Siapa yang memakai</h3>
                    <ul>
                        <li><b>Admin / Pemateri</b>: masuk lewat web dan mengelola semua modul sesuai role-nya.</li>
                        <li><b>Peserta pelatihan</b>: tidak perlu login. Cukup membuka <b>link pribadi</b> yang dikirim lewat WhatsApp untuk absen, mengerjakan kuis, dan melihat nilai. Peserta yang punya akun juga bisa membuka menu <b>Pelatihan Saya</b>.</li>
                    </ul>
                    <h3>Gambaran alur kerja</h3>
                    <ol>
                        <li>Admin menyiapkan data master dan akun pengguna.</li>
                        <li>Pemateri membuat jadwal pelatihan dan memilih peserta, lalu sistem mengirim undangan WhatsApp berisi link pribadi.</li>
                        <li>Pemateri menyiapkan soal kuis (manual, impor, atau dibuat AI).</li>
                        <li>Saat sesi berlangsung: buka presensi, peserta absen, lalu buka kuis.</li>
                        <li>Pemateri menilai essay, memantau hasil, dan mengekspor rekap nilai.</li>
                    </ol>
                    <h3>Istilah penting</h3>
                    <table class="table table-sm table-bordered">
                        <tr><th style="width:200px">Istilah</th><th>Arti</th></tr>
                        <tr><td>Pelatihan</td><td>Satu sesi training berjadwal, lengkap dengan peserta, presensi, dan kuis.</td></tr>
                        <tr><td>Pemateri</td><td>Pengguna yang menjadi trainer sebuah pelatihan.</td></tr>
                        <tr><td>Link pribadi</td><td>Alamat unik per peserta (<code>/training/portal/…</code>). Jangan dibagikan ke orang lain.</td></tr>
                        <tr><td>Divisi / Sub Divisi / Job Level</td><td>Pengelompokan karyawan dan target dokumen.</td></tr>
                        <tr><td>Role</td><td>Kumpulan hak akses yang menentukan menu apa yang terlihat.</td></tr>
                    </table>
                </div>

                {{-- 2 --}}
                <div class="pg-card" id="login">
                    <h2>2. Masuk &amp; keluar</h2>
                    <figure class="pg-shot"><img src="/assets/panduan/login.png" alt="Halaman login" loading="lazy"><figcaption>Halaman login.</figcaption></figure>
                    <h3>Masuk dengan password</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/login-password.gif" alt="Animasi: login dengan password" loading="lazy"><figcaption>Animasi: mengisi username dan password. Data pada animasi hanya contoh.</figcaption></figure>
                    <ol>
                        <li>Buka alamat aplikasi. Halaman login tampil sebagai halaman awal.</li>
                        <li>Isi <b>Username</b>. Kolom ini juga menerima <b>nomor WhatsApp</b> yang terdaftar (format <code>08…</code>, <code>628…</code>, atau <code>+628…</code>).</li>
                        <li>Isi <b>Password</b>. Klik ikon mata untuk melihat isinya.</li>
                        <li>Centang <b>Ingat Saya</b> bila ingin tetap masuk di perangkat ini.</li>
                        <li>Klik <b>Masuk ke Akun</b>. Anda diarahkan ke Dashboard.</li>
                    </ol>
                    <h3>Masuk dengan WhatsApp (OTP)</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/login-whatsapp.gif" alt="Animasi: login via WhatsApp" loading="lazy"><figcaption>Animasi: membuka Login via WhatsApp dan mengisi nomor (tombol Kirim OTP tidak ditekan pada contoh ini).</figcaption></figure>
                    <ol>
                        <li>Klik <b>Login via WhatsApp</b>.</li>
                        <li>Isi <b>Nomor WhatsApp</b>, lalu klik <b>Kirim OTP</b>.</li>
                        <li>Buka WhatsApp, salin kode <b>6 digit</b>, lalu masukkan di kolom <b>Kode OTP</b>. Setelah digit ke-6 diketik, kode otomatis diverifikasi.</li>
                    </ol>
                    <ul>
                        <li>Kode berlaku <b>5 menit</b>. Permintaan kode baru bisa dilakukan setelah hitung mundur <b>60 detik</b> selesai, dan kode lama menjadi tidak berlaku.</li>
                        <li>Pakai <b>Ganti Nomor</b> bila salah memasukkan nomor.</li>
                        <li>Akun harus sudah punya nomor WhatsApp terdaftar. Jika belum, masuk memakai password.</li>
                    </ul>
                    <h3>Keluar</h3>
                    <p>Klik <b>Log Out</b> di bagian bawah menu kiri.</p>
                    <div class="pg-note">Tombol <b>Galeri Video Publik</b> di halaman login membuka kumpulan video materi tanpa perlu login.</div>
                </div>

                {{-- 3 --}}
                <div class="pg-card" id="dashboard">
                    <h2>3. Dashboard</h2>
                    <p>Menu <b>Dashboard</b> menampilkan ringkasan seluruh modul. Setiap kartu bisa diklik untuk membuka halaman terkait.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/dashboard.png" alt="Dashboard" loading="lazy"><figcaption>Dashboard dengan kartu ringkasan dan grafik.</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/sidebar-menu.gif" alt="Animasi: membuka menu samping" loading="lazy"><figcaption>Animasi: membuka submenu Pelatihan dan Settings di menu kiri.</figcaption></figure>
                    <table class="table table-sm table-bordered">
                        <tr><th style="width:220px">Kartu / grafik</th><th>Isi</th></tr>
                        <tr><td>Total Pengguna</td><td>Jumlah akun aktif.</td></tr>
                        <tr><td>Jadwal Pelatihan</td><td>Jumlah pelatihan, dan berapa yang sedang berlangsung.</td></tr>
                        <tr><td>Video Materi</td><td>Jumlah video di galeri.</td></tr>
                        <tr><td>Peserta Pelatihan</td><td>Total peserta terdaftar di semua pelatihan.</td></tr>
                        <tr><td>Dokumen Modul</td><td>Jumlah dokumen aktif.</td></tr>
                        <tr><td>Hasil Evaluasi Kuis</td><td>Jumlah hasil kuis pelatihan beserta rata-rata nilai.</td></tr>
                        <tr><td>Grafik</td><td>Peserta per sesi, sebaran nilai kuis (Sangat Baik ≥ 85, Baik 70–84, Cukup 55–69, Kurang &lt; 55), penonton video, dan komposisi konten. Pindah antara tampilan grafik dan tabel lewat tab.</td></tr>
                    </table>
                </div>

                {{-- 4 --}}
                <div class="pg-card" id="masterdata">
                    <h2>4. Data master</h2>
                    <p>Menu <b>Settings</b> berisi data dasar yang dipakai di banyak tempat: <b>Divisi</b>, <b>Sub Divisi</b>, <b>Job Level</b>, dan <b>Document Type</b>. Isi bagian ini lebih dulu sebelum membuat pengguna atau dokumen.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/divisi.png" alt="Data Divisi" loading="lazy"><figcaption>Halaman Data Divisi.</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/divisi-tambah.gif" alt="Animasi: menambah divisi" loading="lazy"><figcaption>Animasi: klik Tambah Divisi, isi nama, lalu simpan (pada contoh ini dibatalkan).</figcaption></figure>
                    <h3>Menambah data</h3>
                    <ol>
                        <li>Buka <b>Settings</b>, lalu pilih halaman yang dituju.</li>
                        <li>Klik tombol tambah (<b>Tambah Divisi</b>, <b>Tambah Sub Divisi</b>, <b>Tambah Job Level</b>, dst.).</li>
                        <li>Isi nama. Untuk Sub Divisi, pilih juga <b>Main Divisi</b>-nya.</li>
                        <li>Klik <b>Add</b>.</li>
                    </ol>
                    <h3>Mengubah dan menghapus</h3>
                    <ul>
                        <li><b>Edit</b> membuka halaman ubah nama, lalu simpan.</li>
                        <li><b>Hapus</b> menghapus data setelah konfirmasi.</li>
                    </ul>
                    <div class="pg-warn pg-note">
                        <b>Perhatikan:</b> nama otomatis disimpan dengan <b>HURUF BESAR</b>. Data yang masih dipakai (misalnya divisi yang dimiliki pengguna) sebaiknya tidak dihapus karena bisa menimbulkan error. <b>Job Level</b> dengan ID 1 bersifat khusus: pemiliknya mendapat akses penuh seperti Admin.
                    </div>
                </div>

                {{-- 5 --}}
                <div class="pg-card" id="user">
                    <h2>5. Pengguna</h2>
                    <p>Menu <b>Settings → User</b> (halaman <i>Data User</i>) untuk mengelola akun.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/user-create.gif" alt="Animasi: mengisi form tambah user" loading="lazy"><figcaption>Animasi: mengisi form tambah user (data contoh, tidak disimpan).</figcaption></figure>
                    <h3>Menambah user</h3>
                    <ol>
                        <li>Klik <b>Tambah User</b>.</li>
                        <li>Isi data, lalu klik <b>Simpan</b>.</li>
                    </ol>
                    <table class="table table-sm table-bordered">
                        <tr><th style="width:170px">Kolom</th><th style="width:90px">Wajib</th><th>Keterangan</th></tr>
                        <tr><td>ID Karyawan</td><td>Tidak</td><td>Contoh: KRY-001.</td></tr>
                        <tr><td>Nama Lengkap</td><td>Ya</td><td>Disimpan dengan huruf besar.</td></tr>
                        <tr><td>User Name</td><td>Ya</td><td>Harus unik.</td></tr>
                        <tr><td>Email</td><td>Tidak</td><td></td></tr>
                        <tr><td>No WhatsApp</td><td>Tidak*</td><td>Contoh <code>08xxxxxxxxxx</code>. *Wajib diisi bila pengguna ingin login OTP atau menerima undangan pelatihan.</td></tr>
                        <tr><td>Password</td><td>Ya</td><td>Saat edit, kosongkan jika tidak ingin mengubah.</td></tr>
                        <tr><td>Role</td><td>Ya</td><td>Pilihan standar: Admin, Trainer, Staff.</td></tr>
                        <tr><td>Job Level, Divisi</td><td>Ya</td><td></td></tr>
                        <tr><td>Sub Divisi</td><td>Tidak</td><td>Daftar muncul setelah Divisi dipilih.</td></tr>
                    </table>
                    <h3>Mencari, mengedit, menonaktifkan</h3>
                    <ul>
                        <li>Kolom <b>Cari user / ID…</b> mencari berdasarkan nama, username, ID karyawan, email, dan nomor WA.</li>
                        <li><b>Edit</b> mengubah data. User yang nonaktif harus diaktifkan dulu.</li>
                        <li><b>Nonaktifkan</b> membuat user tidak bisa login, tanpa menghapus datanya. <b>Aktifkan</b> mengembalikannya.</li>
                    </ul>
                    <h3>Impor dan ekspor Excel</h3>
                    <ol>
                        <li>Klik <b>Download Template</b> dan isi file <code>user_template.xlsx</code> dengan kolom: <code>id_karyawan, full_name, username, email, no_wa, password, divisi, sub_divisi, job_level</code>.</li>
                        <li>Klik <b>Import</b>, pilih file, lalu <b>Import</b>.</li>
                        <li><b>Export All</b> mengunduh daftar user ke Excel.</li>
                    </ol>
                    <div class="pg-warn pg-note">
                        <ul class="mb-0">
                            <li>Nama <b>divisi</b> dan <b>job_level</b> di file harus sama persis dengan data master, jika tidak impor berhenti.</li>
                            <li>Username duplikat menghentikan impor. Baris sebelumnya tetap tersimpan.</li>
                            <li>Password kosong diganti password bawaan sistem. Minta pengguna menggantinya, atau isi password sendiri.</li>
                            <li><b>User hasil impor belum punya role.</b> Atur role di <a href="#role">Role &amp; Hak Akses</a> agar menu muncul.</li>
                        </ul>
                    </div>
                </div>

                {{-- 6 --}}
                <div class="pg-card" id="role">
                    <h2>6. Role &amp; hak akses</h2>
                    <p>Menu <b>Settings → Role &amp; Hak Akses</b> menentukan fitur apa yang boleh dibuka tiap pengguna. Menu di sisi kiri hanya muncul bila pengguna punya izinnya.</p>
                    <h3>Role bawaan</h3>
                    <ul>
                        <li><b>Admin</b>: semua fitur. Nama dan izinnya tidak dapat diubah atau dihapus.</li>
                        <li><b>Trainer</b>: dashboard, video, pelatihan (termasuk soal dan kuis pelatihan), dan dokumen.</li>
                        <li><b>Staff</b>: dashboard, menonton dan berkomentar di video, Pelatihan Saya, dan melihat dokumen.</li>
                    </ul>
                    <h3>Membuat role baru</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/roles-create.gif" alt="Animasi: membuat role baru" loading="lazy"><figcaption>Animasi: mengisi nama role, memakai Pilih Semua / Hapus Semua, lalu mencentang izin satu per satu.</figcaption></figure>
                    <ol>
                        <li>Klik <b>Tambah Role Baru</b>.</li>
                        <li>Isi <b>Nama Role</b> (harus unik).</li>
                        <li>Centang izin per kelompok. Tombol <b>Pilih Semua</b>, <b>Batal Semua</b>, dan <b>Pilih Bagian Ini</b> mempercepat pemilihan.</li>
                        <li>Klik <b>Simpan Role Baru</b>.</li>
                    </ol>
                    <h3>Mengubah izin role</h3>
                    <p>Di tab <b>Daftar Role &amp; Hak Akses Fitur</b>, klik <b>Atur Hak Akses</b> pada kartu role, ubah centang, lalu <b>Simpan Perubahan Hak Akses</b>. Role yang masih dipakai pengguna tidak dapat dihapus.</p>
                    <h3>Memberi role ke pengguna</h3>
                    <p>Di tab <b>Penetapan Role Pengguna</b>, cari nama, pilih role di kolom <b>Ubah Role</b>, lalu klik <b>Simpan</b>. Setiap pengguna memiliki satu role.</p>
                    <p>Daftar lengkap izin ada di bagian <a href="#akses">Hak akses per fitur</a>.</p>
                </div>

                {{-- 7 --}}
                <div class="pg-card" id="pelatihan">
                    <h2>7. Pelatihan</h2>
                    <p>Menu <b>Pelatihan</b> berisi <b>Jadwal Pelatihan</b>, <b>Tambah Pelatihan</b>, dan <b>Pelatihan Saya</b>.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/training-list.gif" alt="Animasi: memfilter dan mencari pelatihan" loading="lazy"><figcaption>Animasi: memfilter status <b>Dijadwalkan</b> lalu mencari topik di daftar Jadwal Pelatihan.</figcaption></figure>
                    <h3>7.1 Membuat jadwal pelatihan</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/training-create.gif" alt="Animasi: mengisi form pelatihan" loading="lazy"><figcaption>Animasi: mengisi judul, tanggal, tautan Zoom, dan deskripsi. Daftar peserta di kanan sengaja diburamkan pada animasi ini.</figcaption></figure>
                    <ol>
                        <li>Klik <b>Tambah Pelatihan</b>.</li>
                        <li>Isi <b>Judul / Topik Pelatihan</b>, pilih <b>Pemateri (Trainer)</b>, <b>Tanggal Pelaksanaan</b>, <b>Jam Mulai</b>, dan <b>Jam Selesai</b>.</li>
                        <li>Isi <b>Tautan Zoom / Google Meet</b>. Link ini ditampilkan ke peserta.</li>
                        <li>Isi <b>Deskripsi / Silabus Materi</b> (opsional) dan pilih dokumen materi (opsional).</li>
                        <li>Pilih tampilan kuis: <b>formal</b> (ujian standar) atau <b>game</b> (gaya Kahoot).</li>
                        <li>Atur opsi: buka presensi sejak awal, wajib unggah screenshot bukti Zoom, dan kirim WhatsApp saat jadwal disimpan (aktif secara bawaan).</li>
                        <li>Pilih peserta. Bisa <b>Pilih Semua</b>, memfilter per divisi, atau mencari nama. Minimal satu peserta.</li>
                        <li>Klik <b>Simpan Pelatihan</b>.</li>
                    </ol>
                    <div class="pg-note">Pelatihan baru berstatus <b>Dijadwalkan</b>, presensi dan kuis terkunci. Setiap peserta otomatis mendapat link pribadi.</div>

                    <h3>7.2 Halaman detail pelatihan</h3>
                    <p>Klik <b>Detail</b> pada daftar untuk membuka halaman kendali sesi.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/training-show.png" alt="Detail pelatihan" loading="lazy"><figcaption>Detail pelatihan: info sesi, materi, dan Kontrol Sesi Pelatihan di sisi kanan.</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/training-controls.gif" alt="Animasi: kontrol sesi pelatihan" loading="lazy"><figcaption>Animasi: melihat Kontrol Sesi Pelatihan, membuka jendela Lampirkan Dokumen, lalu Menu Opsi.</figcaption></figure>
                    <h3>Kontrol Sesi Pelatihan</h3>
                    <table class="table table-sm table-bordered">
                        <tr><th style="width:190px">Kontrol</th><th>Fungsi</th></tr>
                        <tr><td>Status Pelatihan</td><td><b>Dijadwalkan</b>, <b>Berlangsung</b>, <b>Selesai</b>, <b>Dibatalkan</b>. Bisa diganti kapan saja, tanpa urutan wajib. Kartu sesi Zoom di portal peserta tampil saat status <b>Berlangsung</b>.</td></tr>
                        <tr><td>Akses Presensi</td><td><b>Buka Presensi</b> / <b>Kunci Presensi</b>.</td></tr>
                        <tr><td>Akses Kuis</td><td><b>Buka Kuis</b> / <b>Kunci Kuis</b>. Bawaannya terkunci.</td></tr>
                        <tr><td>Bukti Foto / SS</td><td><b>Wajibkan Foto</b> / <b>Jadikan Opsional</b>: peserta wajib mengunggah screenshot bukti Zoom saat absen hadir.</td></tr>
                        <tr><td>Mode Kuis</td><td><b>Pindah ke Game</b> / <b>Pindah ke Formal</b>.</td></tr>
                        <tr><td>Broadcast WA ke Peserta</td><td>Mengirim undangan (judul, pemateri, jadwal, link Zoom, link pribadi) ke semua peserta. Hasilnya: jumlah berhasil dan gagal.</td></tr>
                    </table>
                    <h3>Materi &amp; dokumen</h3>
                    <ul>
                        <li><b>Lampirkan Dokumen</b>: pilih dokumen dari modul Document agar peserta bisa membukanya di portal. <b>Lepas</b> hanya melepas lampiran, dokumen tidak terhapus.</li>
                        <li><b>Upload Video Materi</b>: unggah rekaman (MP4, MKV, WEBM, MOV, maks. 2 GB) atau tempel link YouTube / Google Drive / Zoom Cloud Recording. Satu pelatihan memiliki satu video, dan video masuk ke galeri Video Materi.</li>
                    </ul>
                    <h3>Daftar peserta &amp; nilai</h3>
                    <p>Di bawah kartu statistik (total peserta, tingkat kehadiran, penyelesaian kuis, rata-rata nilai) terdapat tabel peserta. Gunakan kolom pencarian untuk memfilter. Kolom <b>Aksi</b> tiap peserta berisi:</p>
                    <ul>
                        <li><b>Lembar Jawaban</b> (atau <b>Lembar Jawaban &amp; Review Essay</b>): melihat jawaban, nilai, dan catatan integritas (jumlah pindah tab). Di sini Pemateri memberi nilai essay.</li>
                        <li><b>Salin Link Peserta</b> dan <b>Kirim Notifikasi WA</b>: untuk satu peserta.</li>
                        <li><b>Atur Presensi Manual</b>: ubah status (Belum Absen / Hadir / Tidak Hadir) dan isi catatan.</li>
                        <li><b>Reset Presensi</b>: mengembalikan ke Belum Absen dan menghapus foto bukti.</li>
                        <li><b>Reset Kuis Peserta</b>: menghapus hasil kuis agar peserta bisa mengerjakan ulang. Ini satu-satunya cara mengulang kuis.</li>
                    </ul>
                    <h3>Menilai essay</h3>
                    <ol>
                        <li>Buka <b>Lembar Jawaban &amp; Review Essay</b> peserta.</li>
                        <li>Isi <b>Nilai Essay (0–100)</b> dan <b>Catatan Evaluasi</b> (opsional), lalu klik <b>Simpan Nilai</b>.</li>
                    </ol>
                    <p>Nilai akhir = gabungan nilai pilihan ganda dan essay sesuai proporsi jumlah soal. Sebelum essay dinilai, peserta hanya melihat <i>nilai sementara</i> pilihan ganda.</p>
                    <h3>Mengekspor rekap</h3>
                    <p>Buka <b>Menu Opsi → Export Rekap Nilai</b> untuk mengunduh file Excel rekap pelatihan.</p>
                    <h3>Mengedit dan menghapus</h3>
                    <ul>
                        <li><b>Menu Opsi → Edit</b> (atau <b>Aksi → Edit</b> di daftar) mengubah jadwal, status, dan peserta. <b>Hati-hati:</b> peserta yang dicentang keluar akan terhapus beserta datanya, dan peserta baru tidak otomatis dikirimi WhatsApp.</li>
                        <li><b>Hapus</b> menghapus jadwal setelah konfirmasi.</li>
                    </ul>
                    <h3>Pelatihan Saya</h3>
                    <p>Menu <b>Pelatihan → Pelatihan Saya</b> menampilkan sesi yang diikuti pengguna yang sedang login, lengkap dengan status kehadiran dan nilai kuis. Klik <b>Buka Sesi</b> untuk masuk ke portal peserta.</p>
                </div>

                {{-- 8 --}}
                <div class="pg-card" id="soal">
                    <h2>8. Soal kuis pelatihan</h2>
                    <p>Buka dari detail pelatihan lewat tombol <b>Kelola Soal Kuis</b>. Ada dua tipe soal: <b>Pilihan Ganda</b> (2–6 pilihan, A–F, satu kunci) dan <b>Essay / Uraian</b> (dinilai manual oleh Pemateri).</p>
                    <h3>Tambah manual</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/training-questions.gif" alt="Animasi: menambah soal manual" loading="lazy"><figcaption>Animasi: Tambah Manual, mengisi pertanyaan dan pilihan jawaban (soal contoh tidak disimpan).</figcaption></figure>
                    <ol>
                        <li>Klik <b>Tambah Manual</b>.</li>
                        <li>Pilih <b>Tipe Soal</b>, tulis <b>Pertanyaan Soal</b>.</li>
                        <li>Pilihan ganda: isi <b>Pilihan A</b> dan <b>B</b> (wajib), C–F (opsional), lalu pilih <b>Kunci Jawaban Benar</b>. Essay: isi <b>Pedoman Kunci Jawaban</b> (opsional).</li>
                        <li>Isi <b>Catatan Pembahasan</b> (opsional, tampil di hasil peserta), lalu <b>Simpan Soal</b>.</li>
                    </ol>
                    <h3>Impor Excel / CSV</h3>
                    <ol>
                        <li>Klik <b>Download Template</b>, isi sesuai kolom: <code>tipe_soal, pertanyaan, kunci_jawaban, pilihan_a … pilihan_f, penjelasan</code> (tipe: PG atau ESSAY).</li>
                        <li>Klik <b>Import Excel / CSV</b> dan pilih file (xlsx, xls, csv; maks. 10 MB).</li>
                    </ol>
                    <div class="pg-warn pg-note"><b>Hapus 4 baris contoh</b> di template sebelum impor, karena ikut ter-impor. Bila ada baris yang salah, impor berhenti di baris itu dan baris sebelumnya tetap tersimpan.</div>
                    <h3>Import Teks Cepat</h3>
                    <p>Tempel soal bertulis langsung. Pisahkan soal dengan baris kosong atau nomor (<code>1.</code>, <code>Soal 1)</code>), tulis pilihan <code>A.</code> sampai <code>F.</code>, lalu kunci dengan <code>KUNCI:</code> atau <code>JAWABAN:</code>. Penjelasan memakai <code>PENJELASAN:</code> atau <code>PEMBAHASAN:</code>. Untuk essay tulis <code>TIPE: essay</code>. Tombol <b>Isi Contoh</b> menampilkan format yang benar.</p>
                    <h3>Buat soal dengan AI</h3>
                    <ol>
                        <li>Klik <b>Buat Soal via AI</b> (atau <b>Buat Soal AI</b> di detail pelatihan).</li>
                        <li>Pilih minimal satu dokumen materi (PDF, DOCX, atau teks). Dokumen terpilih juga ikut dilampirkan ke pelatihan.</li>
                        <li>Atur <b>Jumlah Soal</b>, <b>Tipe Soal</b>, <b>Tingkat Kesulitan</b>, dan <b>Instruksi Khusus</b> (opsional).</li>
                        <li>Klik <b>Mulai Generate Soal</b>.</li>
                        <li>Periksa hasilnya di <b>Hasil Soal AI</b>, hilangkan centang soal yang tidak dipakai, lalu klik <b>Simpan n Soal</b>. Soal tidak tersimpan sebelum tombol ini diklik.</li>
                    </ol>
                    <p>Layanan AI memerlukan API key yang disetel administrator server.</p>
                    <h3>Mengedit dan menghapus</h3>
                    <p>Pakai <b>Edit</b> dan <b>Hapus</b> per soal. <b>Hapus Semua Soal</b> menghapus seluruh soal pelatihan tersebut.</p>
                </div>

                {{-- 9 --}}
                <div class="pg-card" id="zoomai">
                    <h2>9. Scan Off Cam Zoom (AI)</h2>
                    <p>Fitur ini membantu mencatat peserta yang kameranya mati saat sesi Zoom, dari <b>screenshot tampilan galeri Zoom</b>.</p>
                    <ol>
                        <li>Di detail pelatihan klik <b>Scan Off Cam Zoom (AI)</b>.</li>
                        <li>Pilih atau seret hingga <b>10 screenshot</b> (JPG, PNG, WEBP).</li>
                        <li>Klik <b>Mulai Pindai … Screenshot dengan AI</b>.</li>
                        <li>Pada <b>Verifikasi Hasil Pindaian AI</b>, periksa nama yang terdeteksi: ubah, hapus, atau <b>Tambah Peserta Manual</b>.</li>
                        <li>Centang <i>Ubah juga status kehadiran menjadi 'Tidak Hadir'</i> bila perlu, lalu simpan.</li>
                    </ol>
                    <div class="pg-note">Tidak ada yang tersimpan sebelum Anda mengonfirmasi hasil. Cara manual: <b>Input Off Cam Manual</b> di tabel peserta, atau tombol +1 / −1 pada menu aksi peserta. Hasil AI adalah bantuan, selalu periksa kembali.</div>
                </div>

                {{-- 10 --}}
                <div class="pg-card" id="dokumen">
                    <h2>10. Dokumen</h2>
                    <p>Menu <b>Document</b> menyimpan dokumen PDF modul, lengkap dengan riwayat versi.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/document-create.png" alt="Tambah dokumen" loading="lazy"><figcaption>Form Tambah Dokumen Baru.</figcaption></figure>
                    <h3>Menambah dokumen</h3>
                    <ol>
                        <li>Klik <b>Tambah Dokumen</b>.</li>
                        <li>Pilih <b>File Dokumen (PDF)</b> (hanya PDF, maks. 50 MB).</li>
                        <li>Pilih <b>Document Type</b>, <b>Job Level</b> (boleh lebih dari satu), <b>Divisi</b>, dan <b>Sub Divisi</b> (opsional). Isi <b>Catatan</b> bila perlu.</li>
                        <li>Klik <b>Simpan</b>. Nama dokumen diambil dari nama file.</li>
                    </ol>
                    <h3>Memperbarui dan mengelola</h3>
                    <ul>
                        <li><b>Aksi → Edit Dokumen</b>: unggah <b>versi baru</b> (PDF) dan isi <b>Catatan Revisi</b>. Versi lama tetap tersimpan.</li>
                        <li>Tombol versi (mis. <code>v2</code>) atau <b>Riwayat Versi</b> menampilkan daftar versi, siapa yang mengubah, dan kapan.</li>
                        <li><b>Buka</b> membuka PDF di tab baru.</li>
                        <li><b>Nonaktifkan Dokumen</b> menyembunyikan dokumen tanpa menghapus, <b>Aktifkan Dokumen</b> mengembalikannya.</li>
                        <li>Gunakan filter <b>Divisi</b>, <b>Type</b>, dan pencarian untuk menemukan dokumen.</li>
                    </ul>
                </div>

                {{-- 11 --}}
                <div class="pg-card" id="video">
                    <h2>11. Video materi</h2>
                    <h3>Galeri (semua orang)</h3>
                    <p>Menu <b>Video Materi</b> (atau <b>Galeri Video Publik</b> di halaman login) menampilkan semua video. Cari lewat kolom pencarian, klik kartu untuk memutar atau membuka halaman tonton.</p>
                    <h3>Komentar &amp; diskusi</h3>
                    <ul>
                        <li>Halaman tonton memiliki bagian <b>Diskusi &amp; Tanya Jawab Materi</b>.</li>
                        <li>Untuk berkomentar Anda harus login dan punya izin komentar. Komentar 2–2000 karakter, balasan hanya satu tingkat.</li>
                        <li>Komentar dapat dihapus oleh penulisnya atau Admin.</li>
                    </ul>
                    <h3>Mengelola video (Admin/Pemateri)</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/video-tambah.gif" alt="Animasi: menambah video dari link" loading="lazy"><figcaption>Animasi: mengisi judul lalu memilih sumber video berupa link.</figcaption></figure>
                    <ol>
                        <li>Klik <b>Tambah Video Baru</b>.</li>
                        <li>Isi <b>Judul Video</b> dan deskripsi.</li>
                        <li>Pilih <b>Sumber Video</b>: <b>file</b> (mp4, webm, ogg, mov, mkv, maks. 2 GB) atau <b>link</b> (YouTube, Google Drive, Vimeo, atau link langsung).</li>
                        <li>Cover/thumbnail bersifat opsional (JPG, PNG, WEBP, maks. 5 MB). Untuk YouTube, cover otomatis.</li>
                        <li>Klik <b>Simpan Video</b> dan <b>jangan tutup halaman</b> selama unggahan berjalan.</li>
                    </ol>
                    <div class="pg-note">Untuk link Google Drive, atur akses berbagi ke <b>"Siapa saja yang memiliki link"</b> agar video bisa diputar. <b>Edit</b> dan <b>Hapus</b> tersedia pada tiap video.</div>
                </div>

                {{-- 12 --}}
                <div class="pg-card" id="peserta">
                    <h2>12. Portal peserta</h2>
                    <p>Bagian ini untuk <b>peserta pelatihan</b>. Anda tidak perlu login.</p>
                    <h3>Membuka portal</h3>
                    <ol>
                        <li>Buka pesan WhatsApp <b>"Pemberitahuan Jadwal Pelatihan"</b>.</li>
                        <li>Ketuk <b>link pribadi</b> di dalam pesan. Portal terbuka di browser HP atau komputer.</li>
                    </ol>
                    <div class="pg-warn pg-note">Link itu khusus untuk Anda. Jangan diteruskan ke orang lain, karena siapa pun yang memegang link dapat mengisi absen dan kuis atas nama Anda.</div>
                    <p>Portal menampilkan judul, pemateri, jadwal, deskripsi, tombol <b>Buka Tautan Zoom</b> (saat sesi berlangsung), serta daftar <b>Dokumen Materi</b>.</p>
                    <h3>Mengisi presensi</h3>
                    <ol>
                        <li>Tunggu sampai pemateri membuka presensi. Selama belum dibuka tampil <i>"Presensi Kehadiran Belum Dibuka"</i>.</li>
                        <li>Jika diwajibkan, unggah <b>screenshot bukti Zoom</b> (JPG, PNG, WEBP, maks. 5 MB).</li>
                        <li>Ketuk tombol hadir. Status berubah menjadi <b>Kehadiran Anda Telah Terverifikasi</b>.</li>
                        <li>Jika tidak bisa hadir, pilih <b>Tidak Hadir</b>, isi <b>Alasan Tidak Hadir</b>, dan ketuk <b>Simpan Keterangan</b>.</li>
                    </ol>
                    <h3>Pelatihan Saya</h3>
                    <p>Jika Anda punya akun, login lalu buka <b>Pelatihan → Pelatihan Saya</b> untuk melihat semua sesi Anda dan membukanya lewat <b>Buka Sesi</b>.</p>
                </div>

                {{-- 13 --}}
                <div class="pg-card" id="kuispeserta">
                    <h2>13. Mengerjakan kuis</h2>
                    <ol>
                        <li>Tunggu hingga kuis dibuka pemateri. Jika belum, tampil pesan <i>"Kuis evaluasi saat ini belum dibuka"</i>.</li>
                        <li>Ketuk <b>Mulai Kerjakan Kuis</b> (atau <b>Mulai Game Kuis</b>).</li>
                        <li>Jawab semua soal. Pilihan ganda: pilih satu jawaban. Essay: tulis di kolom <b>Jawaban Anda</b> (wajib).</li>
                        <li>Ketuk <b>Kirim Jawaban</b> dan konfirmasi.</li>
                    </ol>
                    <h3>Aturan penting</h3>
                    <ul>
                        <li>Kuis hanya bisa dikerjakan <b>satu kali</b>. Mengulang hanya bisa jika pemateri mereset kuis Anda.</li>
                        <li><b>Mode formal:</b> berpindah tab, jendela, atau aplikasi dihitung pelanggaran. Toleransi <b>3 kali</b>, lalu kuis otomatis dikumpulkan dan ditandai <i>Curang (Auto-Submit)</i>. Teks tidak dapat diblok.</li>
                        <li><b>Mode game:</b> 30 detik per soal, ada poin cepat, streak, dan papan peringkat 10 besar. Poin game hanya tampilan, nilai tersimpan adalah persentase pilihan ganda.</li>
                    </ul>
                    <h3>Melihat hasil</h3>
                    <ul>
                        <li>Halaman <b>Hasil Kuis</b> menampilkan <b>Nilai Sementara (Pilihan Ganda)</b>. Setelah essay dinilai berubah menjadi <b>Nilai Akhir</b>.</li>
                        <li><b>Rincian Jawaban &amp; Kunci</b> menampilkan jawaban Anda, kunci, dan pembahasan.</li>
                        <li><b>Catatan Evaluasi dari Pemateri</b> muncul bila pemateri mengisinya.</li>
                    </ul>
                </div>

                {{-- 14 --}}
                <div class="pg-card" id="faq">
                    <h2>14. FAQ &amp; masalah umum</h2>
                    <table class="table table-sm table-bordered">
                        <tr><th style="width:38%">Masalah</th><th>Penyebab / solusi</th></tr>
                        <tr><td>"Username / Nomor WhatsApp atau Password salah."</td><td>Periksa username/nomor dan password. Akun nonaktif tidak dapat login, hubungi admin.</td></tr>
                        <tr><td>OTP tidak masuk</td><td>Pastikan nomor WhatsApp di akun benar. Tunggu hitung mundur 60 detik lalu kirim ulang. Jika muncul "Gateway WhatsApp belum dikonfigurasi", hubungi administrator.</td></tr>
                        <tr><td>Kode OTP salah/kedaluwarsa</td><td>Kode berlaku 5 menit dan hanya satu kali pakai. Minta kode baru.</td></tr>
                        <tr><td>"Role akun Anda tidak memiliki hak akses…"</td><td>Role Anda belum diberi izin fitur itu. Minta admin menyesuaikan di Role &amp; Hak Akses.</td></tr>
                        <tr><td>Menu tidak muncul setelah impor user</td><td>User hasil impor belum punya role. Tetapkan role di tab Penetapan Role Pengguna.</td></tr>
                        <tr><td>Peserta tidak bisa absen</td><td>Presensi belum dibuka. Pemateri klik <b>Buka Presensi</b>. Jika wajib bukti, pastikan foto berformat JPG/PNG/WEBP di bawah 5 MB.</td></tr>
                        <tr><td>Peserta tidak bisa membuka kuis</td><td>Kuis masih terkunci (<b>Buka Kuis</b>), belum ada soal, atau peserta sudah mengerjakan. Untuk mengulang, gunakan <b>Reset Kuis Peserta</b>.</td></tr>
                        <tr><td>WhatsApp gagal terkirim ke peserta</td><td>Nomor WA kosong atau tidak valid. Perbaiki di data user, lalu kirim ulang lewat <b>Kirim Notifikasi WA</b>.</td></tr>
                        <tr><td>Impor Excel gagal di baris tertentu</td><td>Baca pesan <i>Baris ke-N</i>, perbaiki barisnya. Baris sebelumnya mungkin sudah tersimpan, jadi jangan impor ulang seluruh file tanpa memeriksa duplikat.</td></tr>
                        <tr><td>Video Google Drive tidak bisa diputar</td><td>Ubah akses berbagi menjadi "Siapa saja yang memiliki link".</td></tr>
                        <tr><td>Unggah video gagal / terputus</td><td>Periksa ukuran file (maks. 2 GB) dan koneksi. Batas unggah server bisa lebih kecil, hubungi administrator.</td></tr>
                        <tr><td>Soal AI tidak bisa dibuat</td><td>API key AI belum disetel di server, atau dokumen tidak terbaca. Hubungi administrator.</td></tr>
                    </table>
                </div>

                {{-- 15 --}}
                <div class="pg-card" id="akses">
                    <h2>15. Hak akses per fitur</h2>
                    <table class="table table-sm table-bordered">
                        <tr><th>Izin</th><th>Membuka</th><th class="text-center">Admin</th><th class="text-center">Trainer</th><th class="text-center">Staff</th></tr>
                        <tr><td><code>view-dashboard</code></td><td>Dashboard</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">✔</td></tr>
                        <tr><td><code>view-videos</code></td><td>Menu Video Materi</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">✔</td></tr>
                        <tr><td><code>manage-videos</code></td><td>Tambah, ubah, hapus video</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">–</td></tr>
                        <tr><td><code>comment-videos</code></td><td>Berkomentar di video</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">✔</td></tr>
                        <tr><td><code>manage-trainings</code></td><td>Jadwal &amp; kelola pelatihan</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">–</td></tr>
                        <tr><td><code>view-my-trainings</code></td><td>Pelatihan Saya</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">✔</td></tr>
                        <tr><td><code>manage-documents</code></td><td>Kelola dokumen</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">–</td></tr>
                        <tr><td><code>view-documents</code></td><td>Melihat dokumen</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">✔</td></tr>
                        <tr><td><code>manage-users</code></td><td>User</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">–</td></tr>
                        <tr><td><code>manage-roles</code></td><td>Role &amp; Hak Akses</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">–</td></tr>
                        <tr><td><code>manage-master-data</code></td><td>Divisi, Sub Divisi, Job Level, Document Type</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">–</td></tr>
                    </table>
                    <p class="small text-muted mb-0">Tabel menunjukkan pengaturan bawaan. Admin dapat mengubah izin role di menu Role &amp; Hak Akses.</p>
                </div>

            </div>
        </div>
    </div>
</section>
@endsection
