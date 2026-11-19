{{-- Aturan import peserta dari Excel, sama untuk Kelola peserta (detail pelatihan) dan form Tambah Pelatihan. --}}
<ul class="small text-muted pl-3 mb-0">
    <li>Kolom dibaca dari judulnya di baris pertama; hanya sheet pertama yang dibaca. Kolom lain (mis. RSC, Detail Cabang) diabaikan.</li>
    <li><b>No HP wajib</b> di setiap baris, karena undangan dan link portal dikirim lewat WhatsApp.</li>
    <li>Peserta dicocokkan ke master User lewat <b>Employee ID</b>, lalu username, lalu No HP.</li>
    <li>Employee ID yang belum ada di master User <b>dibuatkan user baru</b> (jabatan STAFF, login lewat OTP WhatsApp) bila Full Name dan Divisi terisi dan Anda punya izin Kelola User. Baris yang No HP-nya sudah dipakai user lain ditolak. Tanpa Employee ID tidak dibuat user baru.</li>
    <li>Nama, Job Position, Divisi, dan No HP di master User ikut diperbarui bila Anda berwenang atas user itu. No HP adalah login WhatsApp, jadi hanya admin yang berwenang yang dapat mengubahnya.</li>
    <li>Peserta yang sudah terdaftar tidak didobel. Baris yang ditolak dan catatannya dikabarkan lewat Notifikasi.</li>
</ul>
