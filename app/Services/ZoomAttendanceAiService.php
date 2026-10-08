<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZoomAttendanceAiService
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: config('services.gemini.api_key', env('GEMINI_API_KEY'));
        $this->model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-3.5-flash-lite'));
    }

    /**
     * Check if Gemini API key is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Analyze Zoom screenshots for off-cam participants
     *
     * @param array $images Array of file paths or base64 data URIs
     * @param array $registeredParticipants List of registered participants: [['id' => 1, 'name' => 'John', 'id_karyawan' => '123'], ...]
     * @return array
     * @throws Exception
     */
    public function detectOffCamParticipants(array $images, array $registeredParticipants): array
    {
        if (!$this->isConfigured()) {
            throw new Exception("GEMINI_API_KEY belum dikonfigurasi di file .env!");
        }

        if (empty($images)) {
            throw new Exception("Tidak ada file screenshot Zoom yang diunggah.");
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
            throw new Exception("Format gambar screenshot tidak valid atau file tidak dapat dibaca.");
        }

        // Build prompt instruction with participant list
        $promptText = $this->buildPromptText($registeredParticipants, count($parts));
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
     * Build Gemini prompt
     */
    protected function buildPromptText(array $participants, int $imageCount): string
    {
        $participantsJson = json_encode($participants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = "Anda adalah sistem AI inspektur presensi pelatihan daring yang bertugas mendeteksi peserta yang MEMATIKAN KAMERA (OFF CAM) pada screenshot tampilan galeri (Gallery View) Zoom meeting.\n\n";
        $prompt .= "Konteks:\n";
        $prompt .= "- Dilampirkan {$imageCount} gambar screenshot Zoom meeting.\n";
        $prompt .= "- Di bawah ini adalah DAFTAR PESERTA RESMI PELATIHAN (Database):\n";
        $prompt .= "```json\n{$participantsJson}\n```\n\n";

        $prompt .= "TUGAS & INSTRUKSI ANALISIS:\n";
        $prompt .= "1. Periksa setiap kotak video (tile) partisipan pada seluruh screenshot Zoom yang dilampirkan.\n";
        $prompt .= "2. Klasifikasikan status kamera peserta:\n";
        $prompt .= "   - ON CAM: Menampilkan video nyata wajah/tubuh/ruangan aktif. (JANGAN masukkan ini ke dalam daftar hasil!)\n";
        $prompt .= "   - OFF CAM: Mematikan kamera, ditandai dengan:\n";
        $prompt .= "     * Layar hitam pekat/solid atau abu-abu gelap dengan nama akun di tengah atau pojok.\n";
        $prompt .= "     * Menampilkan foto profil statis / avatar gambar (bukan rekaman video langsung).\n";
        $prompt .= "     * Menampilkan logo/lingkaran inisial huruf nama (misal inisial bulat 'BS', 'MR', dll).\n";
        $prompt .= "     * Ikon kamera disilang merah (jika ada).\n\n";

        $prompt .= "3. BACA NAMA AKUN DI ZOOM:\n";
        $prompt .= "   - Baca teks nama tampilan (Display Name) yang tertera pada kotak yang OFF CAM tersebut.\n\n";

        $prompt .= "4. PENCOCOKAN NAMA CERDAS (FUZZY MATCHING):\n";
        $prompt .= "   - Bandingkan nama di kotak Zoom tersebut dengan DAFTAR PESERTA RESMI PELATIHAN di atas.\n";
        $prompt .= "   - Toleran terhadap:\n";
        $prompt .= "     * Singkatan nama (contoh: 'M. Rizky' cocok ke 'Muhammad Rizky Pratama').\n";
        $prompt .= "     * Nama panggilan atau suku kata depan/belakang.\n";
        $prompt .= "     * Awalan nomor urut atau kelas (contoh: '01_Budi', 'Kls A - Siti').\n";
        $prompt .= "     * Label tambahan Zoom seperti '(Me)', '(Host)', '(Co-host)'.\n";
        $prompt .= "   - Tentukan `similarity_score` (angka 0 sampai 100):\n";
        $prompt .= "     * Jika skor >= 65, masukkan `matched_participant_id` sesuai ID peserta di daftar, dan cantumkan `matched_official_name`.\n";
        $prompt .= "     * Jika nama di Zoom SAMA SEKALI TIDAK COCOK dengan peserta mana pun di database (misal tamu, akun admin/trainer, dsb), set `matched_participant_id` ke null, `matched_official_name` ke null, dan `similarity_score` di bawah 50.\n\n";

        $prompt .= "5. ATURAN PENTING:\n";
        $prompt .= "   - HANYA LAPORKAN YANG OFF CAM. Jangan masukkan peserta yang jelas-jelas ON CAM (kamera aktif)!\n";
        $prompt .= "   - Jika seorang peserta muncul di beberapa screenshot sebagai off cam, cukup laporkan 1 kali (deduplikasi).\n\n";

        $prompt .= "FORMAT OUTPUT (WAJIB JSON VALID TANPA TEKS LAIN):\n";
        $prompt .= "{\n";
        $prompt .= '  "total_screens_analyzed": ' . $imageCount . ",\n";
        $prompt .= '  "total_off_cam_detected": 0,' . "\n";
        $prompt .= '  "detected_off_cam": [' . "\n";
        $prompt .= "    {\n";
        $prompt .= '      "zoom_name": "Teks nama persis yang terbaca di Zoom",' . "\n";
        $prompt .= '      "matched_participant_id": 12,' . "\n";
        $prompt .= '      "matched_official_name": "Nama Resmi di Database",' . "\n";
        $prompt .= '      "similarity_score": 95,' . "\n";
        $prompt .= '      "match_reason": "Alasan pencocokan (misal: singkatan ' . "'M.'" . ' cocok dengan ' . "'Muhammad'" . ')",' . "\n";
        $prompt .= '      "visual_evidence": "Layar hitam polos dengan teks nama di tengah",' . "\n";
        $prompt .= '      "confidence": "high"' . "\n";
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
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . urlencode($this->apiKey);

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
                $lastError = "Gemini API ({$modelName}) Error: " . $errMsg;
                Log::warning("Gemini model {$modelName} failed for Zoom inspection: " . $errMsg);

                if (str_contains($errMsg, 'API_KEY_INVALID') || str_contains($errMsg, 'API key not valid')) {
                    throw new Exception("API Key Gemini tidak valid. Silakan periksa kembali GEMINI_API_KEY Anda di file .env.");
                }
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'API Key Gemini tidak valid')) {
                    throw $e;
                }
                $lastError = $e->getMessage();
            }
        }

        throw new Exception($lastError ?: "Gagal menghubungi layanan Google Gemini Vision. Pastikan koneksi internet stabil.");
    }

    /**
     * Parse and enhance Gemini JSON response
     */
    protected function parseResponse(array $response, array $registeredParticipants): array
    {
        $rawText = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$rawText) {
            throw new Exception("Google Gemini tidak mengembalikan respons teks visual yang valid.");
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
        if (!$decoded || !isset($decoded['detected_off_cam'])) {
            // Check if array directly
            if (is_array($decoded) && isset($decoded[0]['zoom_name'])) {
                $offCamList = $decoded;
            } else {
                Log::error("Failed to parse Gemini Zoom JSON: " . $cleanJson);
                throw new Exception("Format respons dari AI tidak dapat diproses. Silakan coba kembali dengan screenshot yang lebih jelas.");
            }
        } else {
            $offCamList = $decoded['detected_off_cam'];
        }

        // Create quick lookup map for registered participants
        $participantsMap = [];
        foreach ($registeredParticipants as $p) {
            $participantsMap[$p['id']] = $p;
        }

        $processed = [];
        $seenParticipantIds = [];

        foreach ($offCamList as $item) {
            $zoomName = trim($item['zoom_name'] ?? '');
            if (empty($zoomName)) {
                continue;
            }

            $matchedId = $item['matched_participant_id'] ?? null;
            $score = (int) ($item['similarity_score'] ?? 0);
            $reason = $item['match_reason'] ?? '';
            $evidence = $item['visual_evidence'] ?? 'Off Cam';
            $officialName = null;
            $idKaryawan = null;

            // Validate against our registered map
            if ($matchedId && isset($participantsMap[$matchedId])) {
                $participantObj = $participantsMap[$matchedId];
                $officialName = $participantObj['name'] ?? null;
                $idKaryawan = $participantObj['id_karyawan'] ?? null;
            } else {
                // If AI didn't provide participant ID or provided unknown ID, run PHP-side fuzzy check
                $fuzzyResult = $this->localFuzzyMatch($zoomName, $registeredParticipants);
                if ($fuzzyResult && $fuzzyResult['score'] >= 65) {
                    $matchedId = $fuzzyResult['id'];
                    $officialName = $fuzzyResult['name'];
                    $idKaryawan = $fuzzyResult['id_karyawan'];
                    $score = max($score, $fuzzyResult['score']);
                    $reason = $reason ?: "Pencocokan nama otomatis sistem (" . $fuzzyResult['score'] . "%)";
                } else {
                    $matchedId = null;
                }
            }

            // Deduplicate if already detected
            if ($matchedId) {
                if (isset($seenParticipantIds[$matchedId])) {
                    continue; // Skip duplicate
                }
                $seenParticipantIds[$matchedId] = true;
            }

            $processed[] = [
                'zoom_name' => $zoomName,
                'participant_id' => $matchedId,
                'official_name' => $officialName,
                'id_karyawan' => $idKaryawan,
                'similarity_score' => $score,
                'match_reason' => $reason,
                'visual_evidence' => $evidence,
                'confidence' => $item['confidence'] ?? 'medium',
                'is_registered' => !empty($matchedId),
            ];
        }

        return [
            'total_off_cam' => count($processed),
            'off_cam_participants' => $processed,
            'raw_summary' => $decoded['summary'] ?? null,
        ];
    }

    /**
     * Local PHP fuzzy matching fallback using similar_text and token comparison
     */
    protected function localFuzzyMatch(string $zoomName, array $participants): ?array
    {
        $bestMatch = null;
        $highestScore = 0;

        $cleanZoom = $this->sanitizeName($zoomName);
        $zoomTokens = array_values(array_filter(explode(' ', $cleanZoom), fn($t) => strlen($t) > 0));

        if (empty($zoomTokens)) {
            return null;
        }

        foreach ($participants as $p) {
            $cleanOfficial = $this->sanitizeName($p['name']);
            $officialTokens = array_values(array_filter(explode(' ', $cleanOfficial), fn($t) => strlen($t) > 0));

            // Exact match
            if ($cleanZoom === $cleanOfficial) {
                return [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'id_karyawan' => $p['id_karyawan'] ?? '-',
                    'score' => 100,
                ];
            }

            // Calculate standard similar_text percentage
            similar_text($cleanZoom, $cleanOfficial, $percent);

            $matchedWeight = 0;
            $nonNumericTokens = 0;

            foreach ($zoomTokens as $zt) {
                // Ignore numeric prefixes like '01', '02'
                if (is_numeric($zt)) {
                    continue;
                }
                $nonNumericTokens++;

                $tokenMatched = false;
                foreach ($officialTokens as $ot) {
                    if ($zt === $ot) {
                        $matchedWeight += 1.0;
                        $tokenMatched = true;
                        break;
                    } elseif (strlen($zt) >= 3 && (str_starts_with($ot, $zt) || str_starts_with($zt, $ot))) {
                        $matchedWeight += 0.9;
                        $tokenMatched = true;
                        break;
                    } elseif (strlen($zt) === 1 && str_starts_with($ot, $zt)) {
                        // Single initial like "m" matching "muhammad"
                        $matchedWeight += 0.85;
                        $tokenMatched = true;
                        break;
                    }
                }
            }

            if ($nonNumericTokens === 0) {
                continue;
            }

            $zoomCoverage = ($matchedWeight / $nonNumericTokens) * 100;
            $combinedScore = round(($percent * 0.3) + ($zoomCoverage * 0.7));

            if ($combinedScore > $highestScore) {
                $highestScore = $combinedScore;
                $bestMatch = [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'id_karyawan' => $p['id_karyawan'] ?? '-',
                    'score' => (int) $highestScore,
                ];
            }
        }

        return ($highestScore >= 65) ? $bestMatch : null;
    }

    /**
     * Sanitize name string for comparison
     */
    protected function sanitizeName(string $name): string
    {
        // Remove common labels like (Co-host), (Me), (Host), etc.
        $clean = preg_replace('/\((.*?)\)/', '', $name);
        // Remove non-alphanumeric except space
        $clean = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $clean);
        // Lowercase and trim extra spaces
        return strtolower(trim(preg_replace('/\s+/', ' ', $clean)));
    }
}
