<?php

namespace App\Jobs;

use App\Models\Training;
use App\Services\ZoomAttendanceAiService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Memindai screenshot Zoom dengan Gemini untuk mencari peserta yang off-cam. Gemini bisa butuh beberapa menit, jadi
 * dikerjakan worker AI; halaman detail pelatihan mengambil hasilnya lewat polling (zoomScanStatus).
 *
 * Screenshot disimpan controller ke disk "local" (folder zoom-scans/{token}) supaya payload job tetap kecil, lalu
 * dihapus begitu selesai.
 */
class ScanZoomOffCam extends AiTask
{
    public const DIRECTORY = 'zoom-scans';

    /** Jenis gambar dari data URL => ekstensi file. */
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function __construct(string $token, public int $trainingId)
    {
        parent::__construct($token);
    }

    /**
     * Simpan screenshot (data URL base64 yang sudah dikompres browser) untuk dibaca job. Mengembalikan jumlah gambar
     * yang valid.
     *
     * @param  array<int, string>  $images
     */
    public static function storeImages(string $token, array $images): int
    {
        $stored = 0;

        foreach ($images as $image) {
            if (! preg_match('/^data:(image\/[a-z]+);base64,/', $image, $match) || ! isset(self::EXTENSIONS[$match[1]])) {
                continue;
            }

            $binary = base64_decode(substr($image, strlen($match[0])), true);
            if ($binary === false || $binary === '') {
                continue;
            }

            Storage::disk('local')->put(self::directory($token).'/'.$stored.'.'.self::EXTENSIONS[$match[1]], $binary);
            $stored++;
        }

        return $stored;
    }

    protected function run(): array
    {
        $training = Training::with('participants.user')->findOrFail($this->trainingId);

        $participants = $training->participants->map(fn ($participant) => [
            'id' => $participant->id,
            'user_id' => $participant->user_id,
            'name' => $participant->user->nama_lengkap ?? 'Peserta',
            'id_karyawan' => $participant->user->employee_id ?? '-',
            'email' => $participant->user->email ?? '-',
        ])->values()->all();

        $disk = Storage::disk('local');
        $files = collect($disk->files(self::directory($this->token)))
            ->sort(SORT_NATURAL)
            ->map(fn (string $file) => $disk->path($file))
            ->values()
            ->all();

        if ($files === []) {
            throw new RuntimeException('Screenshot Zoom tidak ditemukan di server. Silakan unggah ulang.');
        }

        return [
            'data' => (new ZoomAttendanceAiService)->detectOffCamParticipants($files, $participants),
            'registered_participants' => $participants,
        ];
    }

    protected function cleanup(): void
    {
        Storage::disk('local')->deleteDirectory(self::directory($this->token));
    }

    private static function directory(string $token): string
    {
        return self::DIRECTORY.'/'.$token;
    }
}
