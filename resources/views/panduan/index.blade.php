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
    .pg-card table { font-size: .875rem; }
    .pg-card code { background: var(--gray-100); padding: 1px 6px; border-radius: 4px; color: var(--gray-800); font-size: .8125rem; }
    @media (max-width: 991px) { .pg-wrap { flex-direction: column; } .pg-toc { position: static; width: 100%; max-height: none; } }
</style>

<div class="content-header">
    <div class="container-fluid">
        <x-page-header title="Panduan Penggunaan" subtitle="Cara memakai sistem Modul App dari awal sampai akhir, untuk Admin/Pemateri dan Peserta." eyebrow="Bantuan" />
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
                <a href="#role">6. Jabatan &amp; hak akses</a>
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
                <div class="toc-title mt-2">Modul Belajar</div>
                <a href="#modulbelajar">16. Pretest, materi &amp; posttest</a>
                <a href="#video-kuis">Video dengan pretest/posttest</a>
                <a href="#dokumen-kuis">Dokumen dengan pretest/posttest</a>
                <a href="#mode-quizizz">Mode Quizizz (Game)</a>
            </nav>

            <div class="pg-body">

                {{-- 1 --}}
                <div class="pg-card" id="pengenalan">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-info-circle"></i></span><h2>1. Pengenalan sistem</h2></div>
                    <p>Modul App adalah aplikasi untuk mengelola <b>pelatihan internal</b>: menjadwalkan sesi, mengundang peserta lewat WhatsApp, mencatat kehadiran, memberi kuis evaluasi, serta menyimpan dokumen dan video materi.</p>
                    <h3>Siapa yang memakai</h3>
                    <ul>
                        <li><b>Admin / Pemateri</b>: masuk lewat web dan mengelola modul sesuai hak akses jabatannya.</li>
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
                        <tr><td>Area / Divisi / Posisi</td><td>Pengelompokan karyawan. Area adalah badan usaha tempat karyawan bernaung.</td></tr>
                        <tr><td>Jabatan</td><td>Tingkatan karyawan (ADMIN, STAFF, MANAGER, dst.) sekaligus kumpulan hak akses yang menentukan menu apa yang terlihat. Juga dipakai sebagai target dokumen.</td></tr>
                        <tr><td>Approval Line</td><td>Atasan langsung seorang karyawan. Menentukan tim (bawahan) yang bisa dikelola seorang atasan.</td></tr>
                        <tr><td>Trainer</td><td>Hak akses tambahan (bukan jabatan) untuk mengelola pelatihan, video, dan dokumen.</td></tr>
                    </table>
                </div>

                {{-- 2 --}}
                <div class="pg-card" id="login">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-sign-in-alt"></i></span><h2>2. Masuk &amp; keluar</h2></div>
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
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-tachometer-alt"></i></span><h2>3. Dashboard</h2></div>
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
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-database"></i></span><h2>4. Data master</h2></div>
                    <p>Menu <b>Settings</b> berisi data dasar yang dipakai di banyak tempat: <b>Area</b> (badan usaha), <b>Divisi</b>, <b>Posisi</b>, dan <b>Document Type</b>. Isi bagian ini lebih dulu sebelum membuat pengguna atau dokumen. Data master juga bisa terisi otomatis dari impor user atau <a href="#sync-dnd">Sinkronisasi DnD</a>.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/divisi.png" alt="Data Divisi" loading="lazy"><figcaption>Halaman Data Divisi.</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/divisi-tambah.gif" alt="Animasi: menambah divisi" loading="lazy"><figcaption>Animasi: klik Tambah Divisi, isi nama dan Area, lalu simpan (pada contoh ini dibatalkan).</figcaption></figure>
                    <h3>Menambah data</h3>
                    <ol>
                        <li>Buka <b>Settings</b>, lalu pilih halaman yang dituju.</li>
                        <li>Klik tombol tambah (<b>Tambah Area</b>, <b>Tambah Divisi</b>, <b>Tambah Posisi</b>, dst.).</li>
                        <li>Isi nama. Untuk Divisi, Anda juga bisa memilih <b>Area</b> dan <b>Kepala Divisi</b> (opsional). Kepala Divisi otomatis disarankan sebagai approval line (atasan) bagi karyawan di divisi tersebut.</li>
                        <li>Klik <b>Add</b>.</li>
                    </ol>
                    <h3>Mengubah dan menghapus</h3>
                    <ul>
                        <li><b>Edit</b> membuka halaman ubah nama (untuk Divisi juga <b>Area</b> dan <b>Kepala Divisi</b>), lalu simpan.</li>
                        <li><b>Hapus</b> menghapus data setelah konfirmasi.</li>
                    </ul>
                    <div class="pg-warn pg-note">
                        <b>Perhatikan:</b> nama otomatis disimpan dengan <b>HURUF BESAR</b> dan harus unik. <b>Area</b> tidak dapat dihapus selama masih dipakai karyawan. Data lain yang masih dipakai (misalnya divisi yang dimiliki pengguna) sebaiknya juga tidak dihapus.
                    </div>
                </div>

                {{-- 5 --}}
                <div class="pg-card" id="user">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-users"></i></span><h2>5. Pengguna</h2></div>
                    <p>Menu <b>Settings → User</b> (halaman <i>Data User</i>) untuk mengelola akun.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/user-create.gif" alt="Animasi: mengisi form tambah user" loading="lazy"><figcaption>Animasi: mengisi form tambah user (data contoh, tidak disimpan).</figcaption></figure>
                    <h3>Menambah user</h3>
                    <ol>
                        <li>Klik <b>Tambah User</b>.</li>
                        <li>Isi data, lalu klik <b>Simpan</b> (saat edit: <b>Simpan Perubahan</b>).</li>
                    </ol>
                    <table class="table table-sm table-bordered">
                        <tr><th style="width:170px">Kolom</th><th style="width:90px">Wajib</th><th>Keterangan</th></tr>
                        <tr><td colspan="3"><b>Identitas Pengguna</b></td></tr>
                        <tr><td>ID Karyawan</td><td>Tidak</td><td>Harus unik bila diisi. Contoh: 25.02.001.</td></tr>
                        <tr><td>Nama Lengkap</td><td>Ya</td><td>Disimpan dengan huruf besar.</td></tr>
                        <tr><td>No. HP</td><td>Tidak*</td><td>Nomor WhatsApp Indonesia yang valid, contoh <code>081234567890</code>. Harus unik. Dipakai untuk login OTP dan undangan pelatihan. *Wajib bila pengguna ingin login OTP. Hanya ADMIN yang dapat mengisi atau mengubahnya.</td></tr>
                        <tr><td>Email</td><td>Tidak</td><td></td></tr>
                        <tr><td>Area</td><td>Ya</td><td>Badan usaha karyawan.</td></tr>
                        <tr><td>Divisi</td><td>Ya</td><td>Tidak harus sesuai dengan Area.</td></tr>
                        <tr><td>Jabatan</td><td>Ya</td><td>Lihat <a href="#role">Jabatan &amp; hak akses</a>.</td></tr>
                        <tr><td>Posisi</td><td>Tidak</td><td></td></tr>
                        <tr><td>Approval Line</td><td>Ya*</td><td>Atasan karyawan. *Wajib bila jabatannya mewajibkan approval line. Bila tidak wajib, boleh pilih <i>-- Tanpa Approval Line --</i>. Setelah Divisi dipilih, pilihan dikelompokkan menjadi <i>Satu Divisi</i>, <i>Atasan &amp; Manajemen</i>, dan <i>Karyawan Lainnya (Lintas Divisi)</i>, dan Kepala Divisi otomatis disarankan. Tidak boleh diri sendiri atau membentuk lingkaran atasan.</td></tr>
                        <tr><td colspan="3"><b>Akses Data</b> (hanya untuk jabatan ADMIN, dan hanya terlihat oleh super admin)</td></tr>
                        <tr><td>Cakupan Badan Usaha</td><td>Ya</td><td><b>Semua Badan Usaha</b> menjadikan karyawan ini super admin (wajib mencentang <i>Saya yakin menjadikan karyawan ini super admin</i>). <b>Badan Usaha Tertentu</b>: pilih minimal satu area di kolom <b>Badan Usaha</b>. Untuk admin baru, pilihan bawaannya Badan Usaha Tertentu dengan area karyawan itu sendiri. Anda tidak dapat membatasi akses data akun Anda sendiri.</td></tr>
                        <tr><td colspan="3"><b>Login</b></td></tr>
                        <tr><td>Username</td><td>Ya</td><td>Tanpa spasi, harus unik, disimpan dengan huruf kecil.</td></tr>
                        <tr><td>Password</td><td>Ya</td><td>Minimal 8 karakter. Saat edit, kosongkan jika tidak ingin mengubah.</td></tr>
                        <tr><td colspan="3"><b>Hak Akses Tambahan</b></td></tr>
                        <tr><td>Trainer</td><td>Tidak</td><td>Memberi akses kelola pelatihan, video, dan dokumen di atas hak akses jabatannya. Hanya pengguna dengan izin <code>manage-roles</code> yang dapat mengubahnya.</td></tr>
                    </table>
                    <div class="pg-note">Admin dengan Badan Usaha Tertentu tidak dapat mengubah <b>Username</b>, <b>Password</b>, dan <b>No. HP</b> karyawan yang timnya mencakup karyawan di luar cakupannya. Kolom tersebut terkunci dengan keterangan di form.</div>
                    <h3>Mencari, mengedit, mengarsipkan</h3>
                    <ul>
                        <li>Filter <b>Area</b>, <b>Divisi</b>, <b>Jabatan</b>, <b>Posisi</b>, dan <b>Atasan</b>, serta kolom pencarian (nama, username, ID karyawan, email, No. HP). Daftar menampilkan 25 user per halaman.</li>
                        <li>Tab <b>Semua</b> berisi user aktif, tab <b>Arsip</b> berisi user yang diarsipkan.</li>
                        <li>Kolom <b>Akses Data</b> menunjukkan cakupan admin, dan <b>Last Seen</b> menunjukkan waktu login terakhir (<i>Inactive</i> bila belum pernah login).</li>
                        <li><b>Arsipkan</b> membuat user tidak bisa login, tanpa menghapus datanya. <b>Aktifkan</b> di tab Arsip mengembalikannya. User arsip tidak dapat diedit sebelum diaktifkan, dan Anda tidak dapat mengarsipkan akun sendiri.</li>
                        <li>Di tab Semua, centang beberapa user untuk aksi massal <b>Ubah Posisi Massal</b> atau <b>Ubah Approval Line Massal</b>, lalu klik <b>Terapkan</b>. User yang akan membentuk lingkaran atasan dilewati.</li>
                    </ul>
                    <h3>Cakupan data</h3>
                    <ul>
                        <li><b>Super admin</b> (jabatan ADMIN dengan <i>Semua Badan Usaha</i>) melihat dan mengelola semua karyawan.</li>
                        <li><b>ADMIN dengan Badan Usaha Tertentu</b> melihat karyawan di area pilihannya dan timnya sendiri, tetapi tidak melihat akun ADMIN lain dan tidak dapat memberikan jabatan ADMIN.</li>
                        <li><b>Jabatan lain dengan izin <code>manage-users</code></b> hanya melihat dan mengelola timnya sendiri (bawahan langsung dan satu tingkat di bawahnya menurut approval line). User baru yang ditambahkannya otomatis memakai dia sebagai approval line (kolom Approval Line terkunci), dan dia tidak dapat mengisi No. HP.</li>
                    </ul>
                    <div class="pg-note">Minimal harus selalu ada satu super admin. Sistem menolak perubahan yang menghapus super admin terakhir.</div>
                    <h3>Impor dan ekspor</h3>
                    <p>Menu <b>Import/Export</b> (hanya super admin) berisi <b>Export User</b>, <b>Download Template</b>, <b>Import User (Excel)</b>, dan <b>Import JSON (Talenta)</b>.</p>
                    <ol>
                        <li>Klik <b>Download Template</b> dan isi kolom: <code>nama_lengkap, username, id_karyawan, no_hp, email, role, area, divisi, position, approval, password</code>.</li>
                        <li>Klik <b>Import User (Excel)</b>, pilih file (xlsx, xls, csv; maks. 10 MB), lalu <b>Import</b>.</li>
                        <li><b>Export User</b> mengunduh daftar user ke Excel.</li>
                    </ol>
                    <div class="pg-warn pg-note">
                        <ul class="mb-0">
                            <li>Kolom <code>role</code> berisi nama jabatan dan jabatan itu harus sudah ada. Kolom <code>approval</code> berisi nama lengkap atau ID karyawan atasan. Kolom <code>area</code> dan <code>divisi</code> wajib diisi.</li>
                            <li><code>username</code> boleh kosong; sistem membuatnya dari nama lengkap. No. HP atau email yang tidak valid membuat baris itu gagal.</li>
                            <li>User dicocokkan berdasarkan <code>id_karyawan</code>, lalu <code>username</code>. User arsip yang cocok diaktifkan kembali.</li>
                            <li>Area, divisi, dan posisi yang belum ada dibuat otomatis.</li>
                            <li>Password kosong pada user baru diganti password bawaan sistem. Minta pengguna menggantinya, atau isi password sendiri. Kolom <code>password</code> diabaikan untuk user yang sudah ada.</li>
                            <li>Jabatan ADMIN dari file dibatasi ke badan usaha user itu sendiri (tidak menjadi super admin).</li>
                            <li>Setiap baris diproses sendiri. Baris yang gagal dilewati dan baris lain tetap tersimpan. Hasilnya tampil sebagai <i>Import selesai. Berhasil: X user. Gagal: N baris.</i> beserta daftar <i>Baris N: …</i> (maks. 20 kesalahan).</li>
                        </ul>
                    </div>
                    <h3>Import JSON (Talenta)</h3>
                    <p>Klik <b>Import JSON (Talenta)</b>. Di jendela <b>Import User dari File JSON (Talenta)</b>, pilih file (.json atau .txt, maks. 12 MB), lalu klik <b>Import JSON</b>.</p>
                    <ul>
                        <li>User yang sudah ada (dicocokkan lewat employee ID / username, atau nama lengkap untuk user lama tanpa ID karyawan) hanya diperbarui email dan No. HP-nya. User yang cocok hanya lewat nama tidak dapat diubah No. HP-nya.</li>
                        <li>User arsip yang cocok <b>tidak</b> diaktifkan kembali; barisnya gagal dengan catatan.</li>
                        <li>User baru wajib memiliki <b>Employee ID</b>. Profilnya (jabatan, area, divisi, posisi, approval line) disalin dari karyawan aktif non-ADMIN dengan posisi dan organisasi yang sama. Bila tidak ada yang cocok, jabatannya STAFF, <b>title</b> menjadi Posisi, dan divisi diambil dari field divisi/department, atau dari <b>organization</b> bila field itu tidak ada (master dibuat otomatis bila belum ada). Area hanya terisi bila file memiliki field area/branch; periksa dan lengkapi Area user baru setelah import.</li>
                        <li>User baru mendapat password acak dan masuk lewat <b>OTP WhatsApp</b>, atau admin mengatur password dari form edit user. Bila file berisi field <code>initial_password</code> / <code>password</code> (unik, 12–72 karakter), password itu yang dipakai.</li>
                        <li>No. HP yang tidak valid dilewati tanpa menggagalkan baris; catatannya muncul setelah import.</li>
                    </ul>

                    <h3 id="sync-dnd">Sinkronisasi DnD</h3>
                    <p>Tombol <b>Sinkronisasi DnD</b> (super admin) mengambil data karyawan, Area, Divisi, Posisi, dan Jabatan dari sistem DnD. Tombol nonaktif bila koneksi DnD belum diatur di server.</p>
                    <ol>
                        <li>Klik <b>Sinkronisasi DnD</b>. Halaman <b>Preview Sinkronisasi DnD</b> tampil berisi ringkasan (Data di DnD, User baru, Diperbarui, Tidak berubah), master data baru, daftar perubahan, dan baris yang dilewati.</li>
                        <li>Periksa, lalu klik <b>Proses Sinkronisasi</b>, atau <b>Batal</b>. Tidak ada yang berubah sebelum tombol ini diklik. Bila preview terlalu lama dibiarkan, muncul pesan sesi kedaluwarsa dan sinkronisasi perlu diulang.</li>
                    </ol>
                    <ul>
                        <li>User dicocokkan berdasarkan ID karyawan, lalu username.</li>
                        <li>User baru mendapat password acak dan login memakai OTP WhatsApp.</li>
                        <li>User yang hanya ada di aplikasi ini dan user arsip tidak diubah.</li>
                        <li>Jabatan ADMIN dari DnD dibatasi ke badan usaha user itu sendiri. Jabatan baru dari DnD dibuat dengan hak akses seperti STAFF.</li>
                        <li>Baris dengan divisi kosong dilewati. Username atau No. HP yang sudah dipakai orang lain tidak diubah dan dicatat di preview.</li>
                        <li>Sinkronisasi juga berjalan otomatis setiap hari (bawaan pukul 01:00) lewat scheduler.</li>
                    </ul>
                    <div class="pg-note">Pengaturan di <code>.env</code> server: <code>DND_API_URL</code>, lalu <code>DND_API_TOKEN</code> (token Sanctum milik super admin DnD) atau <code>DND_API_LOGIN</code> + <code>DND_API_PASSWORD</code>. <code>DND_SYNC_TIME</code> opsional untuk mengubah jam sinkronisasi otomatis.</div>
                </div>

                {{-- 6 --}}
                <div class="pg-card" id="role">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-user-shield"></i></span><h2>6. Jabatan &amp; hak akses</h2></div>
                    <p>Menu <b>Settings → Jabatan &amp; Hak Akses</b> menentukan fitur apa yang boleh dibuka tiap pengguna. Setiap pengguna memiliki tepat satu jabatan. Menu di sisi kiri hanya muncul bila jabatannya punya izin.</p>
                    <h3>Jabatan bawaan</h3>
                    <table class="table table-sm table-bordered">
                        <tr><th style="width:170px">Jabatan</th><th style="width:110px">Level Grade</th><th>Hak akses bawaan</th></tr>
                        <tr><td>ADMIN</td><td>100</td><td>Semua fitur.</td></tr>
                        <tr><td>STAFF</td><td>10</td><td rowspan="6">Dashboard, menonton dan berkomentar di video, Pelatihan Saya, dan melihat dokumen. Bisa disesuaikan.</td></tr>
                        <tr><td>TEAM LEADER</td><td>20</td></tr>
                        <tr><td>COORDINATOR</td><td>30</td></tr>
                        <tr><td>MANAGER</td><td>40</td></tr>
                        <tr><td>CHIEF</td><td>50</td></tr>
                        <tr><td>BOD</td><td>60</td></tr>
                    </table>
                    <p>Jabatan bawaan tidak dapat diganti nama atau dihapus. Setiap jabatan memiliki <b>Level Grade</b> dan pilihan <b>Wajib memiliki approval line (atasan)</b>.</p>
                    <h3>Membuat jabatan baru</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/roles-create.gif" alt="Animasi: membuat jabatan baru" loading="lazy"><figcaption>Animasi: mengisi nama jabatan dan Level Grade, mencentang approval line, memakai Pilih Semua / Hapus Semua, lalu mencentang izin satu per satu.</figcaption></figure>
                    <ol>
                        <li>Klik <b>Tambah Jabatan Baru</b>.</li>
                        <li>Isi <b>Nama Jabatan</b> (wajib, unik, disimpan dengan huruf besar) dan <b>Level Grade</b> (opsional). Centang <b>Wajib memiliki approval line (atasan)</b> bila perlu.</li>
                        <li>Centang izin per kelompok. Tombol <b>Pilih Semua</b>, <b>Hapus Semua</b>, dan <b>Pilih Bagian Ini</b> mempercepat pemilihan.</li>
                        <li>Klik <b>Simpan Jabatan Baru</b>.</li>
                    </ol>
                    <h3>Mengubah izin jabatan</h3>
                    <p>Di tab <b>Daftar Jabatan &amp; Hak Akses Fitur</b>, klik <b>Atur Hak Akses</b> pada kartu jabatan, ubah centang, lalu <b>Simpan Perubahan Hak Akses</b>. Jabatan yang masih dipakai pengguna tidak dapat dihapus. Izin jabatan ADMIN selalu penuh (<i>Akses Penuh (Semua)</i>) dan tidak dapat diubah.</p>
                    <h3>Menetapkan jabatan dan Trainer</h3>
                    <p>Di tab <b>Penetapan Jabatan Pengguna</b>, cari nama atau username, pilih jabatan di kolom <b>Ubah Jabatan</b>, centang <b>Trainer</b> bila perlu, lalu klik <b>Simpan</b>.</p>
                    <div class="pg-warn pg-note">Hanya super admin yang dapat memberikan atau mengubah jabatan ADMIN. Pengguna yang dijadikan ADMIN dari sini dibatasi ke badan usahanya sendiri; atur cakupannya di form user bila perlu.</div>
                    <div class="pg-note"><b>Trainer</b> bukan jabatan, melainkan hak akses tambahan. Pengguna yang dicentang Trainer mendapat izin <code>manage-trainings</code>, <code>manage-videos</code>, dan <code>manage-documents</code> di atas izin jabatannya. Centang ini juga ada di form user (bagian <b>Hak Akses Tambahan</b>) dan hanya dapat diubah oleh pengguna dengan izin <code>manage-roles</code>.</div>
                    <p>Daftar lengkap izin ada di bagian <a href="#akses">Hak akses per fitur</a>.</p>
                </div>

                {{-- 7 --}}
                <div class="pg-card" id="pelatihan">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-chalkboard-teacher"></i></span><h2>7. Pelatihan</h2></div>
                    <p>Menu <b>Pelatihan</b> berisi <b>Jadwal Pelatihan</b> dan <b>Pelatihan Saya</b>.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/training-list.gif" alt="Animasi: memfilter dan mencari pelatihan" loading="lazy"><figcaption>Animasi: memfilter status <b>Dijadwalkan</b> lalu mencari topik di daftar Jadwal Pelatihan.</figcaption></figure>
                    <h3>7.1 Membuat jadwal pelatihan</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/training-create.gif" alt="Animasi: mengisi form pelatihan" loading="lazy"><figcaption>Animasi: mengisi judul, pemateri, jadwal, tautan Zoom, deskripsi, dan mode kuis, lalu memilih semua peserta. Data pada animasi hanya contoh.</figcaption></figure>
                    <ol>
                        <li>Buka <b>Pelatihan → Jadwal Pelatihan</b>, klik <b>Tambah Pelatihan</b>.</li>
                        <li>Isi <b>Judul / Topik Pelatihan</b>, pilih <b>Pemateri (Trainer)</b>, <b>Tanggal Pelaksanaan</b>, <b>Jam Mulai</b>, dan <b>Jam Selesai</b>.</li>
                        <li>Isi <b>Tautan Zoom / Google Meet</b>. Link ini ditampilkan ke peserta.</li>
                        <li>Isi <b>Deskripsi / Silabus Materi</b> (opsional) dan pilih dokumen materi (opsional).</li>
                        <li>Di <b>Tampilan / Mode Kuis Peserta</b>, pilih <b>Mode Formal (Ujian)</b> atau <b>Mode Game (Quizizz)</b>.</li>
                        <li>Atur opsi: <b>Langsung buka akses presensi kehadiran peserta</b>, wajib unggah screenshot bukti Zoom, dan <b>Kirim notifikasi WhatsApp ke peserta saat jadwal disimpan</b> (aktif secara bawaan).</li>
                        <li>Pilih peserta. Bisa <b>Pilih Semua</b>, memfilter per divisi, atau mencari nama. Minimal satu peserta.</li>
                        <li>Klik <b>Simpan Pelatihan</b>.</li>
                    </ol>
                    <div class="pg-note">Pelatihan baru berstatus <b>Dijadwalkan</b> dan kuisnya terkunci. Presensi juga terkunci, kecuali opsi buka presensi dicentang. Setiap peserta otomatis mendapat link pribadi. Pesan WhatsApp masuk <b>antrean</b> dan dikirim di latar belakang, dan status kirim tiap peserta diperbarui otomatis.</div>
                    <h3>Daftar pelatihan</h3>
                    <p>Halaman <b>Jadwal Pelatihan</b> memiliki tab <b>Semua</b>, <b>Dijadwalkan</b>, <b>Berlangsung</b>, dan <b>Selesai</b>, serta kolom pencarian topik atau pemateri. Menu <b>Aksi</b> tiap baris berisi <b>Kelola Soal Kuis</b>, upload video, <b>Edit Pelatihan</b>, dan <b>Hapus Pelatihan</b>.</p>

                    <h3>7.2 Halaman detail pelatihan</h3>
                    <p>Klik <b>Detail</b> pada daftar untuk membuka halaman kendali sesi.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/training-show.png" alt="Detail pelatihan" loading="lazy"><figcaption>Detail pelatihan: info sesi, materi, dan Kontrol Sesi Pelatihan di sisi kanan.</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/training-controls.gif" alt="Animasi: kontrol sesi pelatihan" loading="lazy"><figcaption>Animasi: melihat Kontrol Sesi Pelatihan, membuka jendela Lampirkan Dokumen, lalu Menu Opsi.</figcaption></figure>
                    <h3>Kontrol Sesi Pelatihan</h3>
                    <table class="table table-sm table-bordered">
                        <tr><th style="width:190px">Kontrol</th><th>Fungsi</th></tr>
                        <tr><td>Status Pelatihan</td><td><b>Dijadwalkan</b>, <b>Berlangsung</b>, <b>Selesai</b>, <b>Dibatalkan</b>. Bisa diganti kapan saja, tanpa urutan wajib. Status tampil sebagai label di portal peserta.</td></tr>
                        <tr><td>Akses Presensi</td><td><b>Buka Presensi</b> / <b>Kunci Presensi</b>.</td></tr>
                        <tr><td>Akses Kuis</td><td><b>Buka Kuis</b> / <b>Kunci Kuis</b>. Bawaannya terkunci. Selama kuis terbuka, peserta tetap bisa absen meskipun presensi dikunci.</td></tr>
                        <tr><td>Bukti Foto / SS</td><td><b>Wajibkan Foto</b> / <b>Jadikan Opsional</b>: peserta wajib mengunggah screenshot bukti Zoom saat absen hadir.</td></tr>
                        <tr><td>Mode Kuis</td><td><b>Pindah ke Game</b> / <b>Pindah ke Formal</b>.</td></tr>
                        <tr><td>Broadcast WA ke Peserta</td><td>Mengirim undangan (judul, pemateri, jadwal, link Zoom, link pribadi) ke semua peserta. Pesan masuk antrean dan dikirim di latar belakang. Sistem menampilkan jumlah pesan yang masuk antrean, dan status tiap peserta diperbarui otomatis.</td></tr>
                    </table>
                    <h3>Materi &amp; dokumen</h3>
                    <ul>
                        <li><b>Lampirkan Dokumen</b>: di jendela <b>Lampirkan Dokumen Materi</b>, pilih dokumen dari modul Document, lalu klik <b>Simpan Lampiran</b>. Peserta bisa membukanya di portal. Untuk melepas lampiran gunakan <b>Lepas</b>; dokumen hanya dilepas dari pelatihan, tidak terhapus.</li>
                        <li><b>Upload Video Materi</b> (di <b>Menu Opsi</b>, baris video, atau menu <b>Aksi</b> di daftar): pilih <b>Upload File Video</b> (MP4, MKV, WEBM, MOV, maks. 2 GB) atau <b>Tautan / Link (YouTube / Drive / Zoom)</b>, lalu klik <b>Simpan ke Video Materi</b>. Satu pelatihan memiliki satu video, dan video masuk ke galeri Video Materi. Bila video sudah ada, tombolnya menjadi <b>Ganti Video</b> / <b>Tonton Video</b>.</li>
                    </ul>
                    <h3>Daftar peserta &amp; nilai</h3>
                    <p>Di bawah kartu statistik (total peserta, tingkat kehadiran, penyelesaian kuis, rata-rata nilai) terdapat tabel peserta. Gunakan kolom pencarian untuk memfilter. Kolom <b>Aksi</b> tiap peserta berisi:</p>
                    <ul>
                        <li><b>Lembar Jawaban</b> (atau <b>Lembar Jawaban &amp; Review Essay</b>, muncul setelah peserta mengumpulkan kuis): melihat jawaban, nilai, dan catatan integritas (jumlah pindah tab). Di sini Pemateri memberi nilai essay.</li>
                        <li><b>Salin Link Peserta</b> dan <b>Kirim Notifikasi WA</b>: untuk satu peserta.</li>
                        <li><b>Atur Presensi Manual</b>: ubah status (Belum Absen / Hadir / Tidak Hadir) dan isi catatan.</li>
                        <li><b>Reset Presensi (Belum Absen)</b>: mengembalikan ke Belum Absen dan menghapus foto bukti. Muncul bila peserta sudah absen.</li>
                        <li><b>Reset Kuis Peserta</b>: menghapus hasil kuis agar peserta bisa mengerjakan ulang. Muncul bila peserta sudah punya hasil kuis. Ini satu-satunya cara mengulang kuis.</li>
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
                        <li><b>Menu Opsi → Edit Informasi Pelatihan</b> (atau <b>Aksi → Edit Pelatihan</b> di daftar) mengubah jadwal, <b>Status Pelatihan</b>, dan peserta, lalu klik <b>Simpan Perubahan</b>. <b>Hati-hati:</b> peserta yang dicentang keluar akan terhapus beserta datanya.</li>
                        <li>Peserta baru hasil edit tidak langsung dikirimi WhatsApp. Kirim lewat <b>Kirim Notifikasi WA</b> atau <b>Broadcast WA ke Peserta</b>. Bila tidak, sistem mengirim undangan otomatis pada hari pelatihan kepada peserta yang belum menerima WhatsApp (dicek tiap 15 menit oleh scheduler).</li>
                        <li><b>Aksi → Hapus Pelatihan</b> di daftar menghapus jadwal setelah konfirmasi.</li>
                    </ul>
                    <h3>Pelatihan Saya</h3>
                    <p>Menu <b>Pelatihan → Pelatihan Saya</b> menampilkan sesi yang diikuti pengguna yang sedang login, lengkap dengan status kehadiran dan nilai kuis. Klik <b>Buka Sesi</b> untuk masuk ke portal peserta.</p>
                </div>

                {{-- 8 --}}
                <div class="pg-card" id="soal">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-question-circle"></i></span><h2>8. Soal kuis pelatihan</h2></div>
                    <p>Buka dari detail pelatihan lewat tombol <b>Kelola Soal Kuis (n)</b>, atau dari menu <b>Aksi</b> di daftar pelatihan. Ada dua tipe soal: <b>Pilihan Ganda</b> (2–6 pilihan, A–F, satu kunci) dan <b>Essay / Uraian</b> (dinilai manual oleh Pemateri).</p>
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
                        <li>Klik <b>Import Excel / CSV</b>, pilih file di <b>Pilih File Spreadsheet</b> (xlsx, xls, csv; maks. 10 MB), lalu klik <b>Import File</b>. Template juga bisa diunduh dari jendela ini lewat <b>Unduh Template Excel (.xlsx)</b>.</li>
                    </ol>
                    <div class="pg-warn pg-note"><b>Hapus 4 baris contoh</b> di template sebelum impor, karena ikut ter-impor. Bila ada baris yang salah, impor berhenti di baris itu dan baris sebelumnya tetap tersimpan.</div>
                    <h3>Import Teks Cepat</h3>
                    <p>Tempel soal bertulis langsung. Pisahkan soal dengan baris kosong atau nomor (<code>1.</code>, <code>Soal 1)</code>), tulis pilihan <code>A.</code> sampai <code>F.</code>, lalu kunci dengan <code>KUNCI:</code> atau <code>JAWABAN:</code>. Penjelasan memakai <code>PENJELASAN:</code>, <code>PEMBAHASAN:</code>, atau <code>CATATAN:</code>. Untuk essay tulis <code>TIPE: essay</code>. Tombol <b>Isi Contoh</b> menampilkan format yang benar. Klik <b>Simpan Semua Soal</b> untuk menyimpan.</p>
                    <h3>Buat soal dengan AI</h3>
                    <ol>
                        <li>Klik <b>Buat Soal via AI</b> (atau <b>Buat Soal AI</b> di detail pelatihan).</li>
                        <li>Di <b>Pilih Dokumen Materi</b>, pilih minimal satu dokumen (PDF, DOCX, TXT, CSV, MD, atau HTML). Dokumen terpilih juga ikut dilampirkan ke pelatihan.</li>
                        <li>Atur <b>Jumlah Soal</b> (5, 10, 15, atau <b>Kustom</b> 1–100), <b>Tipe Soal</b>, <b>Tingkat Kesulitan</b> (<b>Campuran</b>, <b>Mudah</b>, <b>Sedang</b>, <b>Sulit</b>), dan <b>Instruksi Khusus</b> (opsional).</li>
                        <li>Klik <b>Mulai Generate Soal</b>.</li>
                        <li>Periksa hasilnya di <b>Hasil Soal AI</b>, hilangkan centang soal yang tidak dipakai, lalu klik <b>Simpan n Soal</b>. Soal tidak tersimpan sebelum tombol ini diklik.</li>
                    </ol>
                    <p>Layanan AI memerlukan API key. Bila administrator sudah menyetelnya di server, jendela AI menampilkan <b>AI API Key Terhubung (.env)</b>. Bila belum, isi key di kolom <b>Masukkan API Key</b>. Link <b>Ubah Key</b> dipakai untuk memakai key lain.</p>
                    <h3>Mengedit dan menghapus</h3>
                    <p>Pakai <b>Edit</b> dan <b>Hapus</b> per soal. <b>Hapus Semua Soal</b> menghapus seluruh soal pelatihan tersebut.</p>
                </div>

                {{-- 9 --}}
                <div class="pg-card" id="zoomai">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-video-slash"></i></span><h2>9. Scan Off Cam Zoom (AI)</h2></div>
                    <p>Fitur ini membantu mencatat peserta yang kameranya mati saat sesi Zoom, dari <b>screenshot tampilan galeri Zoom</b>.</p>
                    <ol>
                        <li>Di bagian atas detail pelatihan (di samping <b>Kelola Soal Kuis</b>) klik <b>Scan Off Cam Zoom (AI)</b>.</li>
                        <li>Pilih atau seret hingga <b>10 screenshot</b> (JPG, PNG, WEBP).</li>
                        <li>Klik <b>Mulai Pindai n Screenshot dengan AI</b>.</li>
                        <li>Pada <b>Verifikasi Hasil Pindaian AI</b>, periksa nama yang terdeteksi: ubah, hapus, atau <b>Tambah Peserta Manual</b>.</li>
                        <li>Centang <i>Ubah juga status kehadiran menjadi 'Tidak Hadir'</i> bila perlu, lalu klik <b>Konfirmasi &amp; Simpan Catatan Off Cam</b>.</li>
                    </ol>
                    <div class="pg-note">Tidak ada yang tersimpan sebelum Anda mengonfirmasi hasil. Cara manual: klik <b>Input Off Cam Manual</b> di tabel peserta. Kolom aksi berganti menjadi tombol <b>Tandai Off Cam</b>, lalu tombol − / + untuk mengubah jumlah. Klik <b>Selesai Mode Manual</b> bila sudah. Hasil AI adalah bantuan, selalu periksa kembali.</div>
                </div>

                {{-- 10 --}}
                <div class="pg-card" id="dokumen">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-file-alt"></i></span><h2>10. Dokumen</h2></div>
                    <p>Menu <b>Document</b> menyimpan dokumen PDF modul, lengkap dengan riwayat versi.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/document-create.png" alt="Tambah dokumen" loading="lazy"><figcaption>Form Tambah Dokumen Baru, termasuk bagian Pretest &amp; Posttest.</figcaption></figure>
                    <h3>Menambah dokumen</h3>
                    <ol>
                        <li>Klik <b>Tambah Dokumen</b>.</li>
                        <li>Pilih <b>File Dokumen (PDF)</b> (hanya PDF, maks. 50 MB).</li>
                        <li>Pilih <b>Document Type</b>, <b>Jabatan</b> (boleh lebih dari satu, termasuk <b>SEMUA JABATAN</b>), dan <b>Divisi</b>. Isi <b>Catatan</b> bila perlu.</li>
                        <li>Klik <b>Simpan</b>. Nama dokumen diambil dari nama file (huruf besar). Bila memilih lebih dari satu jabatan, sistem membuat satu dokumen untuk setiap jabatan.</li>
                    </ol>
                    <h3>Memperbarui dan mengelola</h3>
                    <ul>
                        <li><b>Aksi → Edit Dokumen</b>: unggah file di <b>Upload Versi Baru (PDF)</b> dan isi <b>Catatan Revisi</b>. Versi lama tetap tersimpan. Document Type, Divisi, dan Jabatan (satu jabatan) juga bisa diubah di sini.</li>
                        <li>Tombol versi (mis. <code>v2</code>) atau <b>Riwayat Versi</b> menampilkan daftar versi, siapa yang mengubah, dan kapan.</li>
                        <li><b>Buka</b> membuka PDF di tab baru.</li>
                        <li><b>Nonaktifkan Dokumen</b> menyembunyikan dokumen dari peserta, pelatihan, dan Modul Belajar tanpa menghapus; dokumen tetap tampil di daftar dengan status <b>NONAKTIF</b>, dan <b>Aktifkan Dokumen</b> mengembalikannya.</li>
                        <li><b>Hapus Dokumen</b> menghilangkan dokumen dari daftar beserta kuis pretest/posttest-nya. Data, file, dan riwayat nilainya tetap tersimpan di sistem (soft delete).</li>
                        <li>Dokumen yang terhapus ada di tab <b>Terhapus</b>. Klik <b>Pulihkan</b> untuk mengembalikannya ke daftar beserta kuis pretest/posttest-nya; status aktif/nonaktifnya sama seperti sebelum dihapus.</li>
                        <li>Gunakan filter <b>Divisi</b>, <b>Type</b>, dan pencarian untuk menemukan dokumen.</li>
                    </ul>
                    <h3 id="dokumen-kuis">Membuat dokumen dengan pretest &amp; posttest</h3>
                    <p>Pretest dikerjakan peserta <b>sebelum</b> dokumen bisa dibuka, posttest <b>setelah</b> dokumen dibuka. Pengaturannya sama dengan video.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/dokumen-kuis-buat.gif" alt="Animasi: membuat dokumen dengan pretest dan posttest" loading="lazy"><figcaption>Animasi: memilih file PDF, Document Type, Jabatan, dan Divisi, menyalakan pretest dan posttest, lalu <b>Simpan</b>. Halaman soal dokumen terbuka dengan status <b>Kuis belum aktif</b>. Data pada animasi hanya contoh.</figcaption></figure>
                    <ol>
                        <li>Buka <b>Document</b> → <b>Tambah Dokumen</b>, lalu pilih file PDF, <b>Document Type</b>, <b>Jabatan</b>, dan <b>Divisi</b>.</li>
                        <li>Di bagian <b>Pretest &amp; Posttest</b>, nyalakan <b>Aktifkan Pretest</b> dan/atau <b>Aktifkan Posttest</b>.</li>
                        <li>Atur <b>Batas percobaan</b> (pretest bawaannya 1 kali, kosong = tanpa batas) dan <b>Nilai minimal lulus (KKM)</b>.</li>
                        <li>Pilih <b>Tampilan pretest &amp; posttest</b>: <b>Mode Formal (Ujian)</b> atau <b>Mode Quizizz (Game)</b> beserta <b>Detik per soal</b>. Lihat <a href="#mode-quizizz">Mode Quizizz</a>.</li>
                        <li>Klik <b>Simpan</b>. Bila memilih satu jabatan, Anda langsung dibawa ke halaman <b>Soal Pretest &amp; Posttest</b> dokumen tersebut. Bila memilih beberapa jabatan, sistem membuat satu dokumen per jabatan, masing-masing dengan bank soal sendiri; buka soalnya lewat <b>Aksi → Soal Pretest/Posttest</b> pada tiap dokumen.</li>
                        <li>Tambahkan soal lewat <b>Buat Soal via AI</b> (dibaca dari isi PDF), <b>Tambah Manual</b>, atau <b>Import Excel / CSV</b>. Kuis aktif setelah ada minimal satu soal.</li>
                    </ol>
                    <div class="pg-note">Untuk dokumen yang sudah ada, buka <b>Aksi → Edit Dokumen</b>, nyalakan pretest/posttest, lalu simpan. Mematikan keduanya hanya menonaktifkan kuis; riwayat nilai tetap tersimpan.</div>
                    <h3>Yang dialami peserta pada dokumen ber-kuis</h3>
                    <p>Untuk dokumen ber-kuis, tombol <b>Buka</b> membuka halaman kuis dokumen: pretest (bila nyala) → buka dokumen → posttest (bila nyala). Bila pretest aktif, <b>semua akun</b> (termasuk admin) wajib pretest dulu; pengelola dokumen mendapat tombol <b>Pratinjau dokumen tanpa pretest</b> di halaman kuis. Dokumen yang dilampirkan ke pelatihan juga dibuka lewat halaman kuis ini dari portal peserta (perlu login). Soal dan rekap nilai ada di menu <b>Aksi → Soal Pretest/Posttest</b> dan <b>Aksi → Rekap Nilai Kuis</b>. Bila satu unggahan dibuat untuk beberapa jabatan, setiap dokumen memiliki bank soal sendiri. Tes dokumen berada di bawah menu <b>Document</b> dan tidak muncul di <b>Modul Saya</b>.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/dokumen-kuis.gif" alt="Animasi: membuka dokumen ber-kuis" loading="lazy"><figcaption>Animasi (peserta): klik <b>Buka</b> pada dokumen ber-kuis, halaman kuis dokumen menampilkan langkah Pretest → Materi → Posttest, lalu <b>Mulai Pretest</b> dan memilih jawaban. Data pada animasi hanya contoh.</figcaption></figure>
                </div>

                {{-- 11 --}}
                <div class="pg-card" id="video">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-play-circle"></i></span><h2>11. Video materi</h2></div>
                    <h3>Galeri (semua orang)</h3>
                    <p>Menu <b>Video Materi</b> (atau <b>Galeri Video Publik</b> di halaman login) menampilkan semua video. Cari lewat kolom pencarian, klik kartu untuk memutar atau membuka halaman tonton.</p>
                    <h3>Komentar &amp; diskusi</h3>
                    <ul>
                        <li>Halaman tonton memiliki bagian <b>Diskusi &amp; Tanya Jawab Materi</b>.</li>
                        <li>Untuk berkomentar Anda harus login dan punya izin komentar. Komentar 2–2000 karakter, balasan hanya satu tingkat.</li>
                        <li>Komentar dapat dihapus oleh penulisnya atau Admin.</li>
                    </ul>
                    <h3>Mengelola video (Admin/Pemateri)</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/video-tambah.gif" alt="Animasi: menambah video dari link" loading="lazy"><figcaption>Animasi: mengisi judul dan deskripsi, lalu memilih sumber video berupa link.</figcaption></figure>
                    <ol>
                        <li>Klik <b>Tambah Video Baru</b>.</li>
                        <li>Isi <b>Judul Video</b> dan deskripsi.</li>
                        <li>Pilih <b>Sumber Video</b>: <b>file</b> (mp4, webm, ogg, mov, mkv, maks. 2 GB) atau <b>link</b> (YouTube, Google Drive, Vimeo, atau link langsung).</li>
                        <li>Cover/thumbnail bersifat opsional (JPG, PNG, WEBP, maks. 5 MB). Bila tidak dipilih, video file mendapat <b>cover otomatis</b> dari detik pertama video (terlihat di pratinjau cover sebelum disimpan), dan link YouTube memakai thumbnail YouTube.</li>
                        <li>Klik <b>Simpan Video</b> dan <b>jangan tutup halaman</b> selama unggahan berjalan.</li>
                    </ol>
                    <div class="pg-note">Untuk link Google Drive, atur akses berbagi ke <b>"Siapa saja yang memiliki link"</b> agar video bisa diputar. <b>Edit</b> dan <b>Hapus</b> tersedia pada tiap video.</div>
                    <p>Video lama yang belum punya cover: buka <b>Edit</b>, klik <b>Ambil cover dari video</b>, lalu <b>Simpan Perubahan</b>. Format yang tidak bisa diputar browser (mis. sebagian file MKV) tidak bisa diambil cover-nya; pilih gambar cover manual.</p>
                    <h3 id="video-kuis">Membuat video dengan pretest &amp; posttest</h3>
                    <p>Pretest dikerjakan peserta <b>sebelum</b> menonton video, posttest <b>setelah</b> video selesai ditonton. Keduanya memakai bank soal yang sama dengan urutan diacak, dan bisa dinyalakan salah satu atau keduanya.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/video-kuis-buat.gif" alt="Animasi: membuat video dengan pretest dan posttest" loading="lazy"><figcaption>Animasi: mengisi judul dan deskripsi, memilih file video (cover otomatis muncul), menyalakan pretest dan posttest dengan batas posttest 3 kali, lalu <b>Simpan Video</b>. Setelah tersimpan, halaman soal terbuka dengan status <b>Kuis belum aktif</b>. Data pada animasi hanya contoh.</figcaption></figure>
                    <ol>
                        <li>Buka <b>Video Materi</b> → <b>Tambah Video Baru</b>, lalu isi judul, deskripsi, sumber video, dan cover seperti biasa.</li>
                        <li>Di bagian <b>Pretest &amp; Posttest</b> (di bawah cover), nyalakan <b>Aktifkan Pretest</b> dan/atau <b>Aktifkan Posttest</b>.</li>
                        <li>Atur <b>Batas percobaan</b> masing-masing. Pretest bawaannya <b>1</b> kali; kosongkan kolom bila boleh diulang tanpa batas. Contoh: posttest diisi <b>3</b> berarti peserta boleh mengulang posttest sampai 3 kali.</li>
                        <li>Isi <b>Nilai minimal lulus (KKM)</b>, bawaannya 70.</li>
                        <li>Pilih <b>Tampilan pretest &amp; posttest</b>: <b>Mode Formal (Ujian)</b> menampilkan semua soal dalam satu halaman, <b>Mode Quizizz (Game)</b> menampilkan satu soal per layar dengan timer. Untuk Quizizz isi <b>Detik per soal</b> (5–300 detik, bawaan 30). Lihat <a href="#mode-quizizz">Mode Quizizz</a>.</li>
                        <li>Klik <b>Simpan Video</b>. Anda langsung dibawa ke halaman <b>Soal Pretest &amp; Posttest</b> video tersebut.</li>
                        <li>Tambahkan soal lewat <b>Buat Soal via AI</b> (dari file video atau link YouTube), <b>Tambah Manual</b>, atau <b>Import Excel / CSV</b>.</li>
                        <li>Setelah ada minimal satu soal, status berubah dari <b>Kuis belum aktif</b> menjadi <b>Kuis aktif</b>, dan sejak itu peserta wajib mengikuti pretest/posttest video ini.</li>
                    </ol>
                    <figure class="pg-shot"><img src="/assets/panduan/kuis-tambah-soal.gif" alt="Animasi: menambah soal sampai kuis aktif" loading="lazy"><figcaption>Animasi: di halaman soal klik <b>Tambah Manual</b>, isi pertanyaan, pilihan A–D, dan <b>Kunci Jawaban</b>, lalu <b>Simpan Soal</b>. Status berubah menjadi <b>Kuis aktif</b>. Langkah yang sama berlaku untuk soal dokumen.</figcaption></figure>
                    <div class="pg-note">Untuk menyalakan kuis pada video yang sudah ada, buka <b>Edit</b> pada video, nyalakan pretest/posttest di bagian yang sama, lalu <b>Simpan Perubahan</b>. Bila video sudah punya soal, link <b>Kelola soal</b> di bagian tersebut membuka halaman soalnya. Halaman soal juga bisa dibuka dari tombol <b>Soal Kuis</b> di kartu galeri atau <b>Kelola Soal</b> di halaman tonton.</div>
                    <h3>Yang dialami peserta pada video ber-kuis</h3>
                    <p>Tes video berada di bawah menu <b>Video Materi</b> dan tidak muncul di <b>Modul Saya</b>.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/video-pretest.gif" alt="Animasi: pretest sebelum menonton video" loading="lazy"><figcaption>Animasi (peserta): membuka video ber-kuis dari galeri, langsung diarahkan ke pretest, mengirim jawaban, lalu <b>Lanjut Tonton Video</b>.</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/video-posttest.gif" alt="Animasi: posttest setelah video selesai" loading="lazy"><figcaption>Animasi (peserta): menonton video sampai selesai, tombol <b>Kerjakan Posttest</b> muncul, lalu membuka posttest.</figcaption></figure>
                    <ul>
                        <li>Video ber-kuis tetap tampil di galeri, tetapi peserta wajib login untuk menontonnya.</li>
                        <li>Pretest nyala: peserta mengerjakan pretest dulu sebelum video bisa ditonton.</li>
                        <li>Posttest nyala: tombol <b>Kerjakan Posttest</b> muncul di bawah video setelah video selesai ditonton.</li>
                        <li>Bila pretest aktif, <b>semua akun</b> (termasuk admin) diarahkan ke pretest sebelum video bisa ditonton. Khusus pengelola video, halaman pretest menampilkan tombol <b>Pratinjau video tanpa pretest</b> untuk mengecek video; pratinjau tidak mengisi nilai.</li>
                        <li>Pengelola video bisa membuka <b>Kelola Soal</b> dan <b>Rekap Nilai</b> dari halaman tonton, atau tombol <b>Soal Kuis</b> di kartu galeri.</li>
                        <li>Soal diacak sekali untuk setiap percobaan. Urutannya tetap sama walaupun halaman soal di-refresh atau ditinggalkan lalu dibuka lagi, sampai jawaban dikirim.</li>
                        <li>Panah kembali di halaman pretest membawa peserta ke Galeri Video (video baru bisa ditonton setelah pretest dikirim).</li>
                        <li>Mematikan pretest/posttest hanya menonaktifkan kuis; riwayat nilai tetap tersimpan.</li>
                    </ul>
                </div>

                {{-- 12 --}}
                <div class="pg-card" id="peserta">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-user-graduate"></i></span><h2>12. Portal peserta</h2></div>
                    <p>Bagian ini untuk <b>peserta pelatihan</b>. Anda tidak perlu login.</p>
                    <h3>Membuka portal</h3>
                    <ol>
                        <li>Buka pesan WhatsApp <b>"Pemberitahuan Jadwal Pelatihan"</b>.</li>
                        <li>Ketuk <b>link pribadi</b> di dalam pesan. Portal terbuka di browser HP atau komputer.</li>
                    </ol>
                    <div class="pg-warn pg-note">Link itu khusus untuk Anda. Jangan diteruskan ke orang lain, karena siapa pun yang memegang link dapat mengisi absen dan kuis atas nama Anda.</div>
                    <p>Portal menampilkan judul, pemateri, jadwal, deskripsi, status sesi, tombol <b>Buka Tautan Zoom</b> (tidak tampil bila pelatihan <b>Dibatalkan</b>), serta daftar <b>Dokumen Materi</b>.</p>
                    <h3>Mengisi presensi</h3>
                    <ol>
                        <li>Tunggu sampai pemateri membuka presensi (atau kuis). Selama belum dibuka tampil <i>"Presensi Kehadiran Belum Dibuka"</i>.</li>
                        <li>Jika diwajibkan, unggah <b>screenshot bukti Zoom</b> (JPG, PNG, WEBP, maks. 5 MB).</li>
                        <li>Ketuk <b>Saya Hadir</b>. Status berubah menjadi <b>Kehadiran Anda Telah Terverifikasi</b>.</li>
                        <li>Jika tidak bisa hadir, pilih <b>Tidak Hadir</b>, isi <b>Alasan Tidak Hadir</b>, dan ketuk <b>Simpan Keterangan</b>. Status berubah menjadi <b>Konfirmasi Tidak Hadir Telah Dicatat</b>.</li>
                    </ol>
                    <h3>Pelatihan Saya</h3>
                    <p>Jika Anda punya akun, login lalu buka <b>Pelatihan → Pelatihan Saya</b> untuk melihat semua sesi Anda dan membukanya lewat <b>Buka Sesi</b>.</p>
                </div>

                {{-- 13 --}}
                <div class="pg-card" id="kuispeserta">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-clipboard-check"></i></span><h2>13. Mengerjakan kuis</h2></div>
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
                        <li>Setelah kuis dikumpulkan, nilai tampil di portal. Klik <b>Lihat Pembahasan</b> untuk membuka halaman <b>Hasil Kuis</b>.</li>
                        <li>Halaman <b>Hasil Kuis</b> menampilkan <b>Nilai Sementara (Pilihan Ganda)</b>. Setelah essay dinilai berubah menjadi <b>Nilai Akhir</b>.</li>
                        <li><b>Rincian Jawaban &amp; Kunci</b> menampilkan jawaban Anda, kunci, dan pembahasan.</li>
                        <li><b>Catatan Evaluasi dari Pemateri</b> muncul bila pemateri mengisinya.</li>
                    </ul>
                </div>

                {{-- 14 --}}
                <div class="pg-card" id="faq">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-life-ring"></i></span><h2>14. FAQ &amp; masalah umum</h2></div>
                    <table class="table table-sm table-bordered">
                        <tr><th style="width:38%">Masalah</th><th>Penyebab / solusi</th></tr>
                        <tr><td>"Username / Nomor WhatsApp atau Password salah."</td><td>Periksa username/nomor dan password. Akun yang diarsipkan tidak dapat login, hubungi admin.</td></tr>
                        <tr><td>OTP tidak masuk</td><td>Pastikan nomor WhatsApp di akun benar. Tunggu hitung mundur 60 detik lalu kirim ulang. Jika muncul "Gateway WhatsApp belum dikonfigurasi", hubungi administrator.</td></tr>
                        <tr><td>Kode OTP salah/kedaluwarsa</td><td>Kode berlaku 5 menit dan hanya satu kali pakai. Minta kode baru. Kode juga batal bila No. HP akun diubah.</td></tr>
                        <tr><td>"Terlalu banyak percobaan login…"</td><td>Login salah terlalu sering (5 kali per menit). Tunggu 1 menit lalu coba lagi.</td></tr>
                        <tr><td>"Role akun Anda tidak memiliki hak akses…"</td><td>Jabatan Anda belum diberi izin fitur itu. Minta admin menyesuaikan di Jabatan &amp; Hak Akses.</td></tr>
                        <tr><td>Menu tidak muncul</td><td>Jabatan pengguna tidak memiliki izin menu tersebut. Sesuaikan izin jabatannya di Jabatan &amp; Hak Akses, atau centang <b>Trainer</b> bila pengguna perlu mengelola pelatihan, video, dan dokumen.</td></tr>
                        <tr><td>Tidak bisa memilih atasan tertentu di Approval Line</td><td>Atasan tidak boleh diri sendiri, karyawan yang menjadi bawahannya (membentuk lingkaran atasan), atau karyawan di luar cakupan data Anda.</td></tr>
                        <tr><td>Peserta tidak bisa absen</td><td>Presensi belum dibuka. Pemateri klik <b>Buka Presensi</b>. Jika wajib bukti, pastikan foto berformat JPG/PNG/WEBP di bawah 5 MB.</td></tr>
                        <tr><td>Peserta tidak bisa membuka kuis</td><td>Kuis masih terkunci (<b>Buka Kuis</b>), belum ada soal, atau peserta sudah mengerjakan. Untuk mengulang, gunakan <b>Reset Kuis Peserta</b>.</td></tr>
                        <tr><td>WhatsApp gagal terkirim ke peserta</td><td>No. HP kosong atau tidak valid. Perbaiki di data user, lalu kirim ulang lewat <b>Kirim Notifikasi WA</b>. Bila status tetap <i>Dalam antrean</i>, minta administrator memastikan queue worker server berjalan.</td></tr>
                        <tr><td>Impor user Excel gagal di baris tertentu</td><td>Baca pesan <i>Baris N: …</i> (nomor baris mengikuti Excel, baris data pertama = baris 2), perbaiki barisnya, lalu impor ulang. Baris lain sudah tersimpan, tetapi impor ulang tidak membuat duplikat karena user dicocokkan lewat ID karyawan / username.</td></tr>
                        <tr><td>Impor soal Excel gagal di baris tertentu</td><td>Impor soal berhenti di baris yang salah dan baris sebelumnya sudah tersimpan. Hapus baris yang sudah masuk dari file sebelum impor ulang agar soal tidak dobel.</td></tr>
                        <tr><td>Video Google Drive tidak bisa diputar</td><td>Ubah akses berbagi menjadi "Siapa saja yang memiliki link".</td></tr>
                        <tr><td>Unggah video gagal / terputus</td><td>Periksa ukuran file (maks. 2 GB) dan koneksi. Batas unggah server bisa lebih kecil, hubungi administrator.</td></tr>
                        <tr><td>Muncul "413 Request Entity Too Large" saat mengunggah</td><td>File lebih besar dari batas unggah server (nginx/PHP), bukan dari aplikasi. Administrator server perlu menaikkan <code>client_max_body_size</code> nginx serta <code>upload_max_filesize</code> dan <code>post_max_size</code> PHP.</td></tr>
                        <tr><td>Video/dokumen langsung membuka pretest</td><td>Pretest untuk materi itu aktif: kerjakan pretest dulu, lalu materi terbuka. Pengelola materi bisa memakai tombol <b>Pratinjau ... tanpa pretest</b> di halaman pretest.</td></tr>
                        <tr><td>Tombol posttest video belum muncul</td><td>Posttest terbuka setelah video ditonton sampai selesai (≥90% durasi untuk file video, sampai akhir untuk YouTube, atau tombol <b>Saya sudah menonton</b> untuk Drive/Vimeo).</td></tr>
                        <tr><td>Pretest/posttest sudah dinyalakan tetapi tidak muncul ke peserta</td><td>Kuis baru aktif setelah ada minimal satu soal. Tambahkan soal lewat <b>Soal Kuis</b> (video) atau <b>Aksi → Soal Pretest/Posttest</b> (dokumen).</td></tr>
                        <tr><td>"Batas ... kali percobaan sudah habis"</td><td>Jumlah percobaan sudah mencapai batas yang diatur pembuat materi/modul. Pengelola bisa menaikkan atau mengosongkan batas percobaan di form edit.</td></tr>
                        <tr><td>Soal AI tidak bisa dibuat</td><td>API key AI belum disetel di server, atau dokumen tidak terbaca. Hubungi administrator.</td></tr>
                    </table>
                </div>

                {{-- 15 --}}
                <div class="pg-card" id="akses">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-key"></i></span><h2>15. Hak akses per fitur</h2></div>
                    <table class="table table-sm table-bordered">
                        <tr><th>Izin</th><th>Membuka</th><th class="text-center">ADMIN</th><th class="text-center">Jabatan lain (default)</th><th class="text-center">+ Trainer</th></tr>
                        <tr><td><code>view-dashboard</code></td><td>Dashboard</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">–</td></tr>
                        <tr><td><code>view-videos</code></td><td>Menu Video Materi</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">–</td></tr>
                        <tr><td><code>manage-videos</code></td><td>Tambah, ubah, hapus video</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">✔</td></tr>
                        <tr><td><code>comment-videos</code></td><td>Berkomentar di video</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">–</td></tr>
                        <tr><td><code>manage-trainings</code></td><td>Jadwal &amp; kelola pelatihan</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">✔</td></tr>
                        <tr><td><code>view-my-trainings</code></td><td>Pelatihan Saya</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">–</td></tr>
                        <tr><td><code>manage-documents</code></td><td>Kelola dokumen</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">✔</td></tr>
                        <tr><td><code>view-documents</code></td><td>Melihat dokumen</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">–</td></tr>
                        <tr><td><code>manage-learning-modules</code></td><td>Kelola Modul Belajar &amp; rekap nilai</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">✔</td></tr>
                        <tr><td><code>view-learning-modules</code></td><td>Modul Saya (mengerjakan modul)</td><td class="text-center">✔</td><td class="text-center">✔</td><td class="text-center">–</td></tr>
                        <tr><td><code>manage-users</code></td><td>User</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">–</td></tr>
                        <tr><td><code>manage-roles</code></td><td>Jabatan &amp; Hak Akses</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">–</td></tr>
                        <tr><td><code>manage-master-data</code></td><td>Area, Divisi, Posisi, Document Type</td><td class="text-center">✔</td><td class="text-center">–</td><td class="text-center">–</td></tr>
                    </table>
                    <p class="small text-muted mb-0">Tabel menunjukkan pengaturan bawaan. Kolom <b>+ Trainer</b> adalah izin yang ditambahkan oleh centang Trainer di atas izin jabatan. Izin jabatan selain ADMIN dapat diubah di menu Jabatan &amp; Hak Akses.</p>
                </div>

                {{-- 16 --}}
                <div class="pg-card" id="modulbelajar">
                    <div class="pg-head"><span class="stat-tile-icon"><i class="fas fa-book-open"></i></span><h2>16. Modul Belajar: pretest, materi &amp; posttest</h2></div>
                    <p>Modul Belajar adalah kuis mandiri (tanpa jadwal/Zoom) yang materinya diambil dari <b>Video Materi</b> dan <b>Dokumen</b>. Alurnya: peserta mengerjakan <b>pretest</b>, mempelajari semua materi, lalu mengerjakan <b>posttest</b>. Pretest dan posttest memakai soal yang sama dengan urutan diacak.</p>
                    <h3>Membuat modul (Admin/Pemateri)</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/modul-buat.gif" alt="Animasi: membuat modul belajar" loading="lazy"><figcaption>Animasi: mengisi judul, deskripsi, dan KKM, lalu menambahkan video dan dokumen ke <b>Materi Modul</b> (contoh, tidak disimpan).</figcaption></figure>
                    <ol>
                        <li>Buka <b>Modul Belajar → Kelola Modul</b>, klik <b>Tambah Modul</b>.</li>
                        <li>Isi judul, deskripsi, <b>Nilai Minimal Lulus (KKM)</b>, dan pilih <b>Divisi</b> / <b>Jabatan</b> sasaran (kosong = semua).</li>
                        <li>Pilih <b>Tampilan pretest &amp; posttest</b>: Mode Formal atau <a href="#mode-quizizz">Mode Quizizz</a> beserta detik per soal.</li>
                        <li>Di <b>Materi Modul</b>, cari video atau dokumen lalu klik <b>Tambah</b>. Atur urutan dengan tombol panah.</li>
                        <li>Klik <b>Simpan &amp; Lanjut Buat Soal</b>. Tambahkan soal pilihan ganda lewat <b>Buat Soal via AI</b>, <b>Tambah Manual</b>, atau <b>Import Excel / CSV</b>.</li>
                        <li>Klik <b>Publikasikan Modul</b>. Modul baru bisa dipublikasikan bila sudah punya minimal 1 materi dan 1 soal.</li>
                    </ol>
                    <div class="pg-note">Soal AI bisa dibuat dari dokumen dan dari video (file upload atau link YouTube publik). Link Google Drive/Vimeo tidak bisa dibaca AI. Pembacaan video memakan waktu beberapa menit; biarkan jendela AI terbuka sampai hasil muncul. Saat meninjau hasil AI, klik huruf A–E untuk menandai kunci jawaban (pilihan kunci ditandai hijau), sunting teks bila perlu, lalu simpan. Cara yang sama dipakai untuk soal pretest/posttest video dan dokumen.</div>
                    <h3>Mengerjakan modul (Peserta)</h3>
                    <figure class="pg-shot"><img src="/assets/panduan/modul-peserta.gif" alt="Animasi: mengerjakan modul belajar" loading="lazy"><figcaption>Animasi: membuka modul dari <b>Modul Saya</b>, melihat tiga langkah (Pretest, Materi, Posttest), lalu <b>Mulai Pretest</b> dan memilih jawaban.</figcaption></figure>
                    <ol>
                        <li>Buka <b>Modul Belajar → Modul Saya</b> dan pilih modul.</li>
                        <li>Kerjakan <b>Pretest</b> (bawaan: satu kali). Materi baru terbuka setelah pretest dikirim.</li>
                        <li>Selesaikan semua materi: video upload/YouTube tercatat selesai setelah ditonton sampai akhir, dokumen tercatat selesai saat dibuka. Video Drive/Vimeo ditandai lewat tombol <b>Saya sudah menonton</b>.</li>
                        <li>Setelah semua materi selesai, <b>Posttest</b> terbuka. Setiap percobaan dan nilainya tetap tersimpan di riwayat.</li>
                        <li>Batas percobaan pretest dan posttest diatur pembuat modul (kosong = tanpa batas). Nilai awal untuk kenaikan nilai adalah pretest pertama.</li>
                    </ol>
                    <h3 id="mode-quizizz">Mode Quizizz (Game)</h3>
                    <p>Pretest dan posttest pada video, dokumen, dan Modul Belajar bisa ditampilkan seperti Quizizz. Pilihannya ada di bagian <b>Tampilan pretest &amp; posttest</b> pada form video, dokumen, atau modul, dan berlaku untuk pretest maupun posttest.</p>
                    <figure class="pg-shot"><img src="/assets/panduan/kuis-mode-atur.gif" alt="Animasi: memilih Mode Quizizz" loading="lazy"><figcaption>Animasi (admin): di form edit video, memilih <b>Mode Quizizz (Game)</b> lalu mengisi <b>Detik per soal</b> 20 (contoh, tidak disimpan).</figcaption></figure>
                    <figure class="pg-shot"><img src="/assets/panduan/kuis-mode-quizizz.gif" alt="Animasi: mengerjakan pretest Mode Quizizz" loading="lazy"><figcaption>Animasi (peserta): membuka video, menekan <b>Mulai Pretest</b>, menjawab satu soal per layar (hijau = benar, merah = salah), lalu layar akhir menampilkan jawaban benar, nilai, poin, dan streak. Data pada animasi hanya contoh.</figcaption></figure>
                    <ul>
                        <li>Satu soal per layar dengan timer. Waktu per soal diatur pembuat; bila waktu habis, soal dihitung salah.</li>
                        <li>Jawaban <b>langsung terkunci</b> setelah dipilih dan hanya ditandai <b>Benar</b> atau <b>Salah</b>. Kunci jawaban tidak ditampilkan, karena pretest dan posttest memakai bank soal yang sama.</li>
                        <li>Bila halaman di-refresh atau ditinggalkan, permainan dilanjutkan dari soal yang belum dijawab; soal yang sudah dijawab tidak bisa diubah.</li>
                        <li><b>Nilai</b> tetap persentase jawaban benar dan dibandingkan dengan KKM, sama seperti Mode Formal. <b>Poin</b> (cepat menjawab + streak) hanya untuk keseruan dan tidak disimpan.</li>
                        <li>Di layar akhir klik <b>Simpan &amp; Lihat Hasil</b>; bila tidak diklik, hasil tersimpan otomatis setelah 8 detik. Percobaan baru tercatat setelah hasil tersimpan.</li>
                        <li>Riwayat, rekap, dan export nilai sama dengan Mode Formal. Tidak ada papan peringkat antar peserta.</li>
                    </ul>

                    <h3>Rekap nilai</h3>
                    <p>Klik <b>Rekap</b> pada modul untuk melihat progres materi, nilai pretest, jumlah percobaan posttest, nilai terbaik dan terakhir, kenaikan nilai, serta status Lulus/Belum Lulus. <b>Riwayat</b> menampilkan jawaban setiap percobaan. <b>Export Excel</b> menghasilkan dua sheet: rekap per peserta dan riwayat semua percobaan.</p>
                </div>

            </div>
        </div>
    </div>
</section>
@endsection
