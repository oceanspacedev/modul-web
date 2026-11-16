<?php

namespace App\Enums;

/**
 * Rekap kamera peserta pelatihan daring, mengikuti cara pengawas merekap random check screenshot:
 *
 * - On Cam: kamera menyala di lebih dari separuh pengecekan (sesekali mati tetap dianggap On Cam).
 * - Tidak Full On Cam: kamera pernah menyala, tetapi hanya separuh pengecekan atau kurang (50:50 termasuk di sini).
 * - Full Off Cam: kamera tidak pernah terlihat menyala.
 *
 * Pengecekan bisa berupa jumlah screenshot (pindaian AI) atau lama waktu (monitoring meeting, dalam detik).
 */
enum CameraStatus: string
{
    case OnCam = 'on_cam';
    case Partial = 'partial';
    case OffCam = 'off_cam';

    public function label(): string
    {
        return match ($this) {
            self::OnCam => 'On Cam',
            self::Partial => 'Tidak Full On Cam',
            self::OffCam => 'Full Off Cam',
        };
    }

    /** Status dari berapa pengecekan kamera terlihat menyala; null bila belum ada pengecekan sama sekali. */
    public static function fromChecks(int $onCam, int $total): ?self
    {
        if ($total <= 0) {
            return null;
        }

        return match (true) {
            $onCam <= 0 => self::OffCam,
            $onCam * 2 > $total => self::OnCam,
            default => self::Partial,
        };
    }

    /**
     * Persentase kamera menyala yang ditampilkan, dibulatkan tanpa bertentangan dengan statusnya (mis. 50,4% yang
     * sudah On Cam tidak tampil sebagai 50%, dan sedikit sekali menyala tidak tampil sebagai 0%).
     */
    public static function percent(int $onCam, int $total): ?int
    {
        $status = self::fromChecks($onCam, $total);
        if ($status === null) {
            return null;
        }

        $percent = (int) round($onCam * 100 / $total);

        return match ($status) {
            self::OffCam => 0,
            self::Partial => min(50, max(1, $percent)),
            self::OnCam => $onCam >= $total ? 100 : min(99, max(51, $percent)),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
