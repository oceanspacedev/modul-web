<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tautan Tidak Valid - Modul Web</title>
    @include('layout.partials.portal-head')
</head>
<body class="page-center">
    <div class="error-card">
        <div class="error-icon">
            <i class="fas fa-link"></i>
        </div>

        @if(request()->is('training/portal/*'))
            <h1 class="h5 mb-2">Tautan tidak valid atau sudah tidak aktif</h1>
            <p class="text-muted mb-4">
                Tautan akses portal pelatihan ini tidak ditemukan atau sudah tidak berlaku.
                Pastikan Anda menggunakan tautan yang dikirimkan melalui WhatsApp dari admin.
            </p>
            <div class="alert alert-light text-left mb-4">
                Jika Anda merasa ini adalah kesalahan, hubungi admin atau pemateri Anda untuk mendapatkan tautan akses yang baru.
            </div>
        @else
            <h1 class="h5 mb-2">Halaman tidak ditemukan</h1>
            <p class="text-muted mb-4">
                Halaman yang Anda cari tidak tersedia.
            </p>
        @endif

        <a href="/" class="btn btn-primary">
            Kembali ke Beranda
        </a>
    </div>
</body>
</html>
