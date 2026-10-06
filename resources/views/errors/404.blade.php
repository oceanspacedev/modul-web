<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tautan Tidak Valid - Modul Web</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 48px 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .error-icon {
            font-size: 3.5rem;
            color: #ffc107;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="error-card shadow-sm">
        <div class="error-icon">
            <i class="fas fa-link"></i>
        </div>

        @if(request()->is('training/portal/*'))
            <h4 class="font-weight-bold text-dark mb-2">Tautan Tidak Valid atau Sudah Tidak Aktif</h4>
            <p class="text-muted mb-4" style="font-size: 0.95rem; line-height: 1.6;">
                Tautan akses portal pelatihan ini tidak ditemukan atau sudah tidak berlaku.<br>
                Pastikan Anda menggunakan tautan yang dikirimkan melalui WhatsApp dari admin.
            </p>
            <div class="alert alert-light border text-left small mb-4 text-muted">
                <i class="fas fa-info-circle mr-1 text-primary"></i>
                Jika Anda merasa ini adalah kesalahan, silakan hubungi admin atau pemateri Anda untuk mendapatkan tautan akses yang baru.
            </div>
        @else
            <h4 class="font-weight-bold text-dark mb-2">Halaman Tidak Ditemukan</h4>
            <p class="text-muted mb-4" style="font-size: 0.95rem;">
                Halaman yang Anda cari tidak tersedia.
            </p>
        @endif

        <a href="/" class="btn btn-primary px-4 py-2 font-weight-bold">
            <i class="fas fa-home mr-1"></i> Kembali ke Beranda
        </a>
    </div>
</body>
</html>
