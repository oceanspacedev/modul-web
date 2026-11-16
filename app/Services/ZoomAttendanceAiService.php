<?php

namespace App\Services;

use App\Enums\CameraStatus;
use App\Support\Meetings\MeetingNameMatcher;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZoomAttendanceAiService
{
    protected ?string $apiKey;

    protected string $model;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-3.5-flash-lite');
    }

    /**
     * Check if Gemini API key is configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Rekap kamera peserta dari screenshot meeting (Zoom, Google Meet, Teams): setiap screenshot satu pengecekan.
     *
     * @param  array  $images  Array of file paths or base64 data URIs
     * @param  array  $registeredParticipants  List of registered participants: [['id' => 1, 'name' => 'John', 'id_karyawan' => '123'], ...]
     * @param  string  $platform  Platform label shown to the model, e.g. "Google Meet"
     *
     * @throws Exception
     */
    public function detectCameraStatus(array $images, array $registeredParticipants, string $platform = 'Meeting'): array
    {
        if (! $this->isConfigured()) {
            throw new Exception('GEMINI_API_KEY belum dikonfigurasi di file .env!');
        }

        if (empty($images)) {
            throw new Exception('Tidak ada file screenshot meeting yang diunggah.');
        }

        $parts = [];

        // Attach each image to prompt parts
        foreach ($images as $index => $img) {
            $imagePart = $this->buildImagePart($img);
            if ($imagePart) {
                $parts[] = $imagePart;
            }
        }

        if (empty($parts)) {
            throw new Exception('Format gambar screenshot tidak valid atau file tidak dapat dibaca.');
        }

        // Build prompt instruction with participant list
        $promptText = $this->buildPromptText($registeredParticipants, count($parts), $platform);
        $parts[] = [
            'text' => $promptText,
        ];

        // Call Gemini Vision API
        $response = $this->callGeminiApi($parts);

        // Parse and enhance results
        $results = $this->parseResponse($response, $registeredParticipants);

        return $results;
    }

    /**
     * Build image part from file path or base64 data
     */
    protected function buildImagePart($img): ?array
    {
        // Case 1: Data URL (e.g. data:image/jpeg;base64,....)
        if (is_string($img) && str_starts_with($img, 'data:image/')) {
            if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $img, $matches)) {
                return [
                    'inline_data' => [
                        'mime_type' => $matches[1],
                        'data' => $matches[2],
                    ],
                ];
            }
        }

        // Case 2: UploadedFile or local file path
        $filePath = null;
        if (is_object($img) && method_exists($img, 'getRealPath')) {
            $filePath = $img->getRealPath();
            $mime = $img->getMimeType() ?: 'image/jpeg';
        } elseif (is_string($img) && file_exists($img)) {
            $filePath = $img;
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => 'image/jpeg',
            };
        }

        if ($filePath && file_exists($filePath)) {
            return [
                'inline_data' => [
                    'mime_type' => $mime,
                    'data' => base64_encode(file_get_contents($filePath)),
                ],
            ];
        }

        return null;
    }

    /**
     * Build Gemini prompt. Setiap screenshot adalah satu kali pengecekan (random check); AI melaporkan status kamera
     * SEMUA peserta yang terlihat di setiap screenshot, lalu rasionya dihitung di PHP (lihat CameraStatus).
     */
    protected function buildPromptText(array $participants, int $imageCount, string $platform = 'Meeting'): string
    {
        $participantsJson = json_encode($participants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = "Anda adalah sistem AI inspektur presensi pelatihan daring yang bertugas mencatat STATUS KAMERA setiap peserta pada screenshot tampilan galeri (Gallery View) {$platform}.\n\n";
        $prompt .= "Konteks:\n";
        $prompt .= "- Dilampirkan {$imageCount} gambar screenshot {$platform}, diambil pada waktu berbeda selama pelatihan (random check). Urutan gambar = screenshot_index 1 sampai {$imageCount}.\n";
        $prompt .= "- Di bawah ini adalah DAFTAR PESERTA RESMI PELATIHAN (Database):\n";
        $prompt .= "```json\n{$participantsJson}\n```\n\n";

        $prompt .= "TUGAS & INSTRUKSI ANALISIS:\n";
        $prompt .= "1. Periksa SETIAP screenshot satu per satu. Untuk setiap kotak video (tile) partisipan, atau setiap baris pada panel daftar peserta, catat namanya dan status kameranya.\n";
        $prompt .= "2. Status kamera (`camera_on`):\n";
        $prompt .= "   - true (ON CAM): Menampilkan video nyata wajah/tubuh/ruangan aktif.\n";
        $prompt .= "   - false (OFF CAM): Mematikan kamera, ditandai dengan:\n";
        $prompt .= "     * Layar hitam pekat/solid atau abu-abu gelap dengan nama akun di tengah atau pojok.\n";
        $prompt .= "     * Menampilkan foto profil statis / avatar gambar (bukan rekaman video langsung).\n";
        $prompt .= "     * Menampilkan logo/lingkaran inisial huruf nama (misal inisial bulat 'BS', 'MR', dll).\n";
        $prompt .= "     * Ikon kamera disilang merah (jika ada).\n";
        $prompt .= "   - Screenshot PANEL DAFTAR PESERTA (Participants list, satu nama per baris): kamera mati bila ikon kamera di sisi kanan barisnya dicoret/disilang (biasanya merah). Ikon kamera tanpa coretan berarti kamera menyala. Ikon mikrofon TIDAK berpengaruh.\n\n";

        $prompt .= "3. BACA NAMA AKUN DI MEETING:\n";
        $prompt .= "   - Baca teks nama tampilan (Display Name) yang tertera pada setiap kotak/baris.\n\n";

        $prompt .= "4. PENCOCOKAN NAMA CERDAS (FUZZY MATCHING):\n";
        $prompt .= "   - Bandingkan nama di kotak {$platform} tersebut dengan DAFTAR PESERTA RESMI PELATIHAN di atas.\n";
        $prompt .= "   - Toleran terhadap:\n";
        $prompt .= "     * Singkatan nama (contoh: 'M. Rizky' cocok ke 'Muhammad Rizky Pratama').\n";
        $prompt .= "     * Nama panggilan atau suku kata depan/belakang.\n";
        $prompt .= "     * Awalan nomor urut atau kelas (contoh: '01_Budi', 'Kls A - Siti').\n";
        $prompt .= "     * Label tambahan platform seperti '(Me)', '(You)', '(Anda)', '(Host)', '(Co-host)', '(Penyelenggara)'.\n";
        $prompt .= "   - Tentukan `similarity_score` (angka 0 sampai 100):\n";
        $prompt .= "     * Jika skor >= 65, masukkan `matched_participant_id` sesuai ID peserta di daftar.\n";
        $prompt .= "     * Jika nama di {$platform} SAMA SEKALI TIDAK COCOK dengan peserta mana pun di database (misal tamu, akun admin/trainer, dsb), set `matched_participant_id` ke null dan `similarity_score` di bawah 50.\n\n";

        $prompt .= "5. ATURAN PENTING:\n";
        $prompt .= "   - Laporkan SEMUA peserta yang terlihat, baik kamera menyala maupun mati.\n";
        $prompt .= "   - Laporkan per screenshot: peserta yang sama muncul lagi di screenshot lain dicatat lagi di screenshot itu. Dalam satu screenshot, satu peserta cukup dicatat 1 kali.\n";
        $prompt .= "   - Gunakan teks nama yang sama persis untuk peserta yang sama di semua screenshot.\n\n";

        $prompt .= "FORMAT OUTPUT (WAJIB JSON VALID TANPA TEKS LAIN):\n";
        $prompt .= "{\n";
        $prompt .= '  "screenshots": ['."\n";
        $prompt .= "    {\n";
        $prompt .= '      "screenshot_index": 1,'."\n";
        $prompt .= '      "participants": ['."\n";
        $prompt .= "        {\n";
        $prompt .= '          "zoom_name": "Teks nama persis yang terbaca di '.$platform.'",'."\n";
        $prompt .= '          "camera_on": false,'."\n";
        $prompt .= '          "matched_participant_id": 12,'."\n";
        $prompt .= '          "similarity_score": 95,'."\n";
        $prompt .= '          "match_reason": "Alasan pencocokan (misal: singkatan '."'M.'".' cocok dengan '."'Muhammad'".')"'."\n";
        $prompt .= "        }\n";
        $prompt .= "      ]\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n";

        return $prompt;
    }

    /**
     * Call Google Gemini API with fallback models
     */
    protected function callGeminiApi(array $parts): array
    {
        $modelsToTry = array_unique([
            $this->model,
            'gemini-2.5-flash',
            'gemini-1.5-flash',
            'gemini-2.0-flash',
            'gemini-3.5-flash-lite',
            'gemini-flash-latest',
        ]);

        $lastError = null;

        foreach ($modelsToTry as $modelName) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=".urlencode($this->apiKey);

            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => $parts,
                    ],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.2,
                ],
            ];

            try {
                $response = Http::timeout(180)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, $payload);

                if ($response->successful()) {
                    return $response->json();
                }

                $errBody = $response->json();
                $errMsg = $errBody['error']['message'] ?? $response->body();
                $lastError = "Gemini API ({$modelName}) Error: ".$errMsg;
                Log::warning("Gemini model {$modelName} failed for Zoom inspection: ".$errMsg);

                if (str_contains($errMsg, 'API_KEY_INVALID') || str_contains($errMsg, 'API key not valid')) {
                    throw new Exception('API Key Gemini tidak valid. Silakan periksa kembali GEMINI_API_KEY Anda di file .env.');
                }
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'API Key Gemini tidak valid')) {
                    throw $e;
                }
                $lastError = $e->getMessage();
            }
        }

        throw new Exception($lastError ?: 'Gagal menghubungi layanan Google Gemini Vision. Pastikan koneksi internet stabil.');
    }

    /**
     * Parse the Gemini JSON response and count, per person, in how many screenshots they appeared and in how many
     * their camera was on. One row per matched participant; unmatched meeting names get one row per name.
     */
    protected function parseResponse(array $response, array $registeredParticipants): array
    {
        $rawText = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (! $rawText) {
            throw new Exception('Google Gemini tidak mengembalikan respons teks visual yang valid.');
        }

        // Clean json backticks if any
        $cleanJson = trim($rawText);
        if (str_starts_with($cleanJson, '```json')) {
            $cleanJson = substr($cleanJson, 7);
        } elseif (str_starts_with($cleanJson, '```')) {
            $cleanJson = substr($cleanJson, 3);
        }
        if (str_ends_with($cleanJson, '```')) {
            $cleanJson = substr($cleanJson, 0, -3);
        }
        $cleanJson = trim($cleanJson);

        $decoded = json_decode($cleanJson, true);
        if (! is_array($decoded) || ! isset($decoded['screenshots']) || ! is_array($decoded['screenshots'])) {
            Log::error('Failed to parse Gemini meeting camera JSON: '.$cleanJson);
            throw new Exception('Format respons dari AI tidak dapat diproses. Silakan coba kembali dengan screenshot yang lebih jelas.');
        }

        $participantsMap = [];
        foreach ($registeredParticipants as $p) {
            $participantsMap[$p['id']] = $p;
        }

        $rows = [];
        foreach ($decoded['screenshots'] as $index => $screenshot) {
            // Within one screenshot a person counts once: camera on if any of their tiles showed video.
            $seen = [];
            foreach ((array) ($screenshot['participants'] ?? []) as $item) {
                $zoomName = trim((string) ($item['zoom_name'] ?? ''));
                if ($zoomName === '' || ! array_key_exists('camera_on', $item)) {
                    continue;
                }

                $matchedId = $item['matched_participant_id'] ?? null;
                $score = (int) ($item['similarity_score'] ?? 0);
                $reason = (string) ($item['match_reason'] ?? '');
                if (! $matchedId || ! isset($participantsMap[$matchedId])) {
                    // The AI gave no or an unknown participant ID: match the name here.
                    $fuzzy = MeetingNameMatcher::match($zoomName, $registeredParticipants);
                    $matchedId = $fuzzy['id'] ?? null;
                    if ($fuzzy !== null) {
                        $score = max($score, $fuzzy['score']);
                        $reason = $reason ?: 'Pencocokan nama otomatis sistem ('.$fuzzy['score'].'%)';
                    }
                }

                $key = $matchedId ? 'p:'.$matchedId : 'n:'.mb_strtolower($zoomName);
                $seen[$key] = ($seen[$key] ?? false) || (bool) $item['camera_on'];

                $rows[$key] ??= [
                    'names' => [],
                    'participant_id' => $matchedId,
                    'score' => 0,
                    'reason' => '',
                    'checks' => 0,
                    'on' => 0,
                ];
                $rows[$key]['names'][$zoomName] = true;
                if ($score >= $rows[$key]['score']) {
                    $rows[$key]['score'] = $score;
                    $rows[$key]['reason'] = $reason;
                }
            }

            foreach ($seen as $key => $cameraOn) {
                $rows[$key]['checks']++;
                $rows[$key]['on'] += $cameraOn ? 1 : 0;
            }
        }

        $processed = [];
        foreach ($rows as $row) {
            $participant = $row['participant_id'] ? $participantsMap[$row['participant_id']] : null;
            $status = CameraStatus::fromChecks($row['on'], $row['checks']);

            $processed[] = [
                'zoom_name' => implode(' / ', array_keys($row['names'])),
                'participant_id' => $row['participant_id'],
                'official_name' => $participant['name'] ?? null,
                'id_karyawan' => $participant['id_karyawan'] ?? null,
                'similarity_score' => $row['score'],
                'match_reason' => $row['reason'],
                'visual_evidence' => self::screenshotEvidence($row['on'], $row['checks']),
                'confidence' => 'medium',
                'is_registered' => $participant !== null,
                'camera_status' => $status->value,
                'camera_on_percent' => CameraStatus::percent($row['on'], $row['checks']),
                'checks' => $row['checks'],
                'on_cam_checks' => $row['on'],
            ];
        }

        return [
            'total_screens_analyzed' => count($decoded['screenshots']),
            'camera_participants' => $processed,
        ];
    }

    /** Bukti status kamera dari screenshot, mis. "Kamera menyala di 3 dari 10 screenshot". */
    public static function screenshotEvidence(int $onCam, int $checks): string
    {
        return match (true) {
            $onCam <= 0 => 'Kamera mati di semua '.$checks.' screenshot',
            $onCam >= $checks => 'Kamera menyala di semua '.$checks.' screenshot',
            default => 'Kamera menyala di '.$onCam.' dari '.$checks.' screenshot',
        };
    }
}
