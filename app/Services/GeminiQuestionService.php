<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Video;
use Exception;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use ZipArchive;

class GeminiQuestionService
{
    protected const API_BASE = 'https://generativelanguage.googleapis.com';

    /** Format video yang dibaca Gemini, per ekstensi file upload. */
    protected const VIDEO_MIME_TYPES = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'ogg' => 'video/ogg',
        'mkv' => 'video/x-matroska',
    ];

    /** Batas tunggu Gemini selesai memproses video upload: 120 x 5 detik. */
    protected const VIDEO_POLL_ATTEMPTS = 120;

    protected ?string $apiKey;

    protected string $model;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-3.5-flash-lite');
    }

    /**
     * Check if API key is configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Video yang bisa dibaca AI: file upload, atau link YouTube publik. Link Drive/Vimeo tidak bisa diakses Gemini.
     */
    public static function supportsVideo(Video $video): bool
    {
        return $video->isFile() ? filled($video->video_file) : $video->youtubeId() !== null;
    }

    /**
     * Generate quiz questions from one or more documents and/or videos
     *
     * @param  array  $documents  List of Document and Video models
     * @param  int  $count  Number of questions to generate
     * @param  string  $type  Question type: 'multiple_choice' or 'essay'
     * @param  string  $difficulty  Difficulty level: 'mudah', 'sedang', 'sulit', 'campuran'
     * @param  string|null  $customPrompt  Additional custom instructions
     *
     * @throws Exception
     */
    public function generateQuestions(
        array $documents,
        int $count = 5,
        string $type = 'multiple_choice',
        string $difficulty = 'campuran',
        ?string $customPrompt = null
    ): array {
        if (! $this->isConfigured()) {
            throw new Exception('GEMINI_API_KEY belum dikonfigurasi. Silakan masukkan API Key Gemini di pengaturan atau file .env!');
        }

        if (empty($documents)) {
            throw new Exception('Tidak ada dokumen yang dipilih sebagai acuan materi.');
        }

        $parts = [];
        $uploadedFiles = [];

        try {
            foreach ($documents as $source) {
                if ($source instanceof Video) {
                    $parts[] = $this->buildVideoPart($source, $uploadedFiles);
                }
            }

            return $this->generateFromParts(
                $parts,
                array_filter($documents, fn ($source) => ! $source instanceof Video),
                $count,
                $type,
                $difficulty,
                $customPrompt
            );
        } finally {
            foreach ($uploadedFiles as $fileName) {
                $this->deleteUploadedFile($fileName);
            }
        }
    }

    /**
     * @param  array  $parts  Video parts that are already prepared
     * @param  array  $documents  List of Document models
     *
     * @throws Exception
     */
    protected function generateFromParts(array $parts, array $documents, int $count, string $type, string $difficulty, ?string $customPrompt): array
    {
        $hasVideo = ! empty($parts);

        // Attach documents to prompt parts
        foreach ($documents as $doc) {
            $filePath = $this->downloadDocument($doc);
            if (! $filePath) {
                continue;
            }

            try {
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $docPart = $this->buildFilePart($filePath, $ext, $doc->name);
            } finally {
                @unlink($filePath);
            }

            if ($docPart) {
                $parts[] = $docPart;
            }
        }

        if (empty($parts)) {
            throw new Exception('File fisik dari dokumen yang dipilih tidak ditemukan pada storage server.');
        }

        // Build prompt instruction
        $promptText = $this->buildPromptText($count, $type, $difficulty, $customPrompt);
        $parts[] = [
            'text' => $promptText,
        ];

        // Call Gemini API
        $response = $this->callGeminiApi($parts, $hasVideo);

        return $this->parseResponse($response, $type);
    }

    /**
     * Part Gemini untuk sebuah video. File upload dikirim lewat Files API (nama file Gemini dicatat di $uploadedFiles
     * agar dihapus setelah selesai); link YouTube dikirim langsung sebagai file_uri.
     *
     * @param  array<int, string>  $uploadedFiles
     *
     * @throws Exception
     */
    protected function buildVideoPart(Video $video, array &$uploadedFiles): array
    {
        if (! self::supportsVideo($video)) {
            throw new Exception("Video \"{$video->title}\" berupa link selain YouTube sehingga tidak bisa dibaca AI. Gunakan video upload atau link YouTube publik.");
        }

        if ($video->isLink()) {
            return ['file_data' => ['file_uri' => 'https://www.youtube.com/watch?v='.$video->youtubeId()]];
        }

        $localPath = $this->downloadFromDisks([$video->video_file], 'gemini-video-');
        if (! $localPath) {
            throw new Exception("File video \"{$video->title}\" tidak ditemukan pada storage server.");
        }

        try {
            $mimeType = self::VIDEO_MIME_TYPES[strtolower(pathinfo($localPath, PATHINFO_EXTENSION))] ?? 'video/mp4';
            $file = $this->uploadFile($localPath, $mimeType, $video->title);
        } finally {
            @unlink($localPath);
        }

        $uploadedFiles[] = $file['name'];
        $file = $this->waitUntilActive($file, $video->title);

        return ['file_data' => ['mime_type' => $file['mimeType'] ?? $mimeType, 'file_uri' => $file['uri']]];
    }

    /**
     * Unggah file ke Gemini Files API (resumable upload; isi file di-stream, tidak dimuat ke memori).
     *
     * @throws Exception
     */
    protected function uploadFile(string $localPath, string $mimeType, string $displayName): array
    {
        $start = Http::timeout(60)
            ->withHeaders([
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) filesize($localPath),
                'X-Goog-Upload-Header-Content-Type' => $mimeType,
            ])
            ->post(self::API_BASE.'/upload/v1beta/files?key='.urlencode($this->apiKey), [
                'file' => ['display_name' => $displayName],
            ]);

        $uploadUrl = $start->header('X-Goog-Upload-URL');
        if (! $start->successful() || ! $uploadUrl) {
            throw new Exception('Gagal memulai unggah video ke Gemini: '.($start->json('error.message') ?? 'HTTP '.$start->status()));
        }

        $stream = Utils::streamFor(fopen($localPath, 'rb'));
        try {
            $upload = Http::timeout(1800)
                ->withHeaders([
                    'X-Goog-Upload-Offset' => '0',
                    'X-Goog-Upload-Command' => 'upload, finalize',
                ])
                ->withBody($stream, $mimeType)
                ->post($uploadUrl);
        } finally {
            $stream->close();
        }

        $file = $upload->json('file');
        if (! $upload->successful() || empty($file['name']) || empty($file['uri'])) {
            throw new Exception('Gagal mengunggah video ke Gemini: '.($upload->json('error.message') ?? 'HTTP '.$upload->status()));
        }

        return $file;
    }

    /**
     * Gemini memproses video beberapa saat setelah diunggah; file baru bisa dipakai saat state ACTIVE.
     *
     * @throws Exception
     */
    protected function waitUntilActive(array $file, string $displayName): array
    {
        for ($attempt = 0; $attempt < self::VIDEO_POLL_ATTEMPTS; $attempt++) {
            $state = $file['state'] ?? 'PROCESSING';

            if ($state === 'ACTIVE') {
                return $file;
            }

            if ($state === 'FAILED') {
                throw new Exception("Gemini gagal memproses video \"{$displayName}\". Pastikan format video didukung (mp4, webm, mov).");
            }

            Sleep::for(5)->seconds();
            $file = Http::timeout(60)->get(self::API_BASE.'/v1beta/'.$file['name'].'?key='.urlencode($this->apiKey))->json() ?: $file;
        }

        throw new Exception("Gemini terlalu lama memproses video \"{$displayName}\". Silakan coba lagi nanti.");
    }

    protected function deleteUploadedFile(string $fileName): void
    {
        try {
            Http::timeout(30)->delete(self::API_BASE.'/v1beta/'.$fileName.'?key='.urlencode($this->apiKey));
        } catch (Exception $e) {
            // File di Gemini juga kedaluwarsa sendiri setelah 48 jam.
            Log::warning("Gagal menghapus file Gemini {$fileName}: ".$e->getMessage());
        }
    }

    /**
     * Copy a Document's file to a local temp file (same extension) and return its path; the caller deletes it.
     *
     * Uploads live on the default disk (S3 on the servers), so the file is read through Storage, not a local path.
     * The "public" disk is checked too for files uploaded before the switch to S3.
     */
    protected function downloadDocument(Document $doc): ?string
    {
        $paths = array_map(fn ($relative) => 'dokumen/'.$relative, array_filter([$doc->versions()->first()?->path, $doc->path]));

        return $this->downloadFromDisks($paths, 'gemini-doc-');
    }

    /**
     * Copy the first of $paths found on the upload disk to a local temp file (same extension); the caller deletes it.
     *
     * @param  array<int, string>  $paths
     */
    protected function downloadFromDisks(array $paths, string $tempPrefix): ?string
    {
        $disks = array_unique([config('filesystems.default'), 'public']);

        foreach (array_unique($paths) as $path) {
            foreach ($disks as $diskName) {
                $disk = Storage::disk($diskName);

                if (! $disk->exists($path)) {
                    continue;
                }

                $tempPath = tempnam(sys_get_temp_dir(), $tempPrefix);
                $localPath = $tempPath.'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));
                rename($tempPath, $localPath);

                $source = $disk->readStream($path);
                $target = fopen($localPath, 'wb');
                stream_copy_to_stream($source, $target);
                fclose($target);
                fclose($source);

                return $localPath;
            }
        }

        return null;
    }

    /**
     * Build Gemini part for a document file
     */
    protected function buildFilePart(string $filePath, string $ext, string $docTitle): ?array
    {
        $fileSize = filesize($filePath);

        // For PDF: if <= 18MB, use inlineData (Gemini understands PDFs natively)
        if ($ext === 'pdf') {
            if ($fileSize <= 18 * 1024 * 1024) {
                return [
                    'inline_data' => [
                        'mime_type' => 'application/pdf',
                        'data' => base64_encode(file_get_contents($filePath)),
                    ],
                ];
            } else {
                // Large PDF: extract text fallback
                $text = $this->extractTextFromPdfFallback($filePath);
                if (! empty($text)) {
                    return [
                        'text' => "--- KONTEN DOKUMEN: {$docTitle} ---\n\n".substr($text, 0, 80000),
                    ];
                }
            }
        }

        // For DOCX: extract text
        if ($ext === 'docx') {
            $text = $this->extractTextFromDocx($filePath);
            if (! empty($text)) {
                return [
                    'text' => "--- KONTEN DOKUMEN: {$docTitle} ---\n\n".substr($text, 0, 80000),
                ];
            }
        }

        // For TXT, CSV, MD
        if (in_array($ext, ['txt', 'csv', 'md', 'html'])) {
            $content = file_get_contents($filePath);

            return [
                'text' => "--- KONTEN DOKUMEN: {$docTitle} ---\n\n".substr($content, 0, 80000),
            ];
        }

        // Generic fallback for any other files under 15MB
        if ($fileSize <= 15 * 1024 * 1024) {
            $mime = mime_content_type($filePath) ?: 'application/octet-stream';

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
     * Extract plain text from DOCX
     */
    protected function extractTextFromDocx(string $filePath): string
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) === true) {
            if (($index = $zip->locateName('word/document.xml')) !== false) {
                $xml = $zip->getFromIndex($index);
                $zip->close();
                // Replace paragraphs and breaks with newlines
                $clean = preg_replace('/<w:p[^>]*>/', "\n", $xml);
                $clean = preg_replace('/<w:br[^>]*>/', "\n", $clean);

                return trim(strip_tags($clean));
            }
            $zip->close();
        }

        return '';
    }

    /**
     * Basic text extraction from PDF
     */
    protected function extractTextFromPdfFallback(string $filePath): string
    {
        $content = @file_get_contents($filePath);
        if (! $content) {
            return '';
        }
        // Simple stream text extraction
        preg_match_all('/\((.*?)\)Tj/s', $content, $matches);
        if (! empty($matches[1])) {
            return implode(' ', $matches[1]);
        }

        return '';
    }

    /**
     * Construct prompt for Gemini
     */
    protected function buildPromptText(int $count, string $type, string $difficulty, ?string $customPrompt): string
    {
        $typeDesc = ($type === 'essay')
            ? 'soal Essay / Uraian yang mendalam dan relevan'
            : 'soal Pilihan Ganda (Multiple Choice) dengan 5 opsi jawaban (A, B, C, D, E)';

        $prompt = "Anda adalah seorang instruktur pelatihan profesional dan pembuat materi ujian berpengalaman.\n\n";
        $prompt .= "TUGAS:\n";
        $prompt .= "Analisis materi yang dilampirkan (dokumen dan/atau video) secara menyeluruh. Buatkan hingga {$count} butir {$typeDesc} berdasarkan materi dan fakta dalam materi tersebut.\n";
        $prompt .= "Jika cakupan materi dokumen luas (misal buku/modul lengkap berbab-bab), sebar pertanyaan ke berbagai topik bab dan hasilkan hingga mencapai target {$count} butir soal.\n";
        $prompt .= "Jika isi materi dokumen terbatas sehingga tidak memungkinkan mencapai {$count} soal tanpa terjadi pengulangan, buatkan sebanyak mungkin butir soal berkualitas maksimal yang secara substantif dapat dirumuskan dari dokumen tersebut.\n\n";
        $prompt .= "PARAMETER:\n";
        $prompt .= "- Target jumlah soal: {$count}\n";
        $prompt .= "- Tipe soal: {$type}\n";
        $prompt .= "- Tingkat kesulitan: {$difficulty}\n";

        if ($customPrompt) {
            $prompt .= "- Catatan/instruksi khusus trainer: {$customPrompt}\n";
        }

        $prompt .= "\nKETENTUAN KUALITAS SOAL:\n";
        $prompt .= "1. Soal HARUS sepenuhnya berbasis pada isi dan fakta dalam materi (isi dokumen, serta penjelasan lisan dan tampilan pada video).\n";
        $prompt .= "2. Buat pertanyaan yang jelas, tidak ambigu, dan menguji pemahaman konsep/prosedur penting, bukan sekadar hafalan nomor halaman.\n";

        if ($type === 'multiple_choice') {
            $prompt .= "3. Untuk setiap soal pilihan ganda, sediakan tepat 5 pilihan (option_a, option_b, option_c, option_d, option_e).\n";
            $prompt .= "4. Pilihan pengecoh (distractor) harus masuk akal, relevan dengan konteks materi, dan memiliki panjang kalimat yang seimbang.\n";
            $prompt .= "5. Tentukan kunci jawaban yang benar pada kolom 'correct_answer' dengan huruf kecil ('a', 'b', 'c', 'd', atau 'e').\n";
            $prompt .= "6. Variasikan letak kunci jawaban (jangan semua opsi 'a' atau 'b').\n";
            $prompt .= "7. Sertakan 'explanation' (pembahasan singkat 1-2 kalimat mengapa jawaban tersebut tepat berdasarkan dokumen).\n";
        } else {
            $prompt .= "3. Kolom option_a s/d option_e diisi string kosong \"\".\n";
            $prompt .= "4. Kolom 'correct_answer' diisi poin-poin kunci jawaban / rubrik penilaian singkat.\n";
            $prompt .= "5. Sertakan 'explanation' berupa penjelasan materi yang diharapkan dari peserta.\n";
        }

        $prompt .= "\nFORMAT OUTPUT (WAJIB JSON MURNI DENGAN SKEMA BERIKUT):\n";
        $prompt .= "{\n";
        $prompt .= '  "questions": ['."\n";
        $prompt .= "    {\n";
        $prompt .= '      "question": "Kalimat pertanyaan lengkap?",'."\n";
        $prompt .= '      "option_a": "Teks opsi A",'."\n";
        $prompt .= '      "option_b": "Teks opsi B",'."\n";
        $prompt .= '      "option_c": "Teks opsi C",'."\n";
        $prompt .= '      "option_d": "Teks opsi D",'."\n";
        $prompt .= '      "option_e": "Teks opsi E",'."\n";
        $prompt .= '      "correct_answer": "a",'."\n";
        $prompt .= '      "explanation": "Penjelasan mengapa opsi tersebut benar..."'."\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n";

        return $prompt;
    }

    /**
     * Call Gemini API endpoint with model fallback
     */
    protected function callGeminiApi(array $parts, bool $hasVideo = false): array
    {
        $modelsToTry = array_unique([
            $this->model,
            'gemini-3.5-flash-lite',
            'gemini-3.5-flash',
            'gemini-3-flash-preview',
            'gemini-flash-latest',
            'gemini-3.8-flash',
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
                    'temperature' => 0.3,
                    // Resolusi rendah memangkas token video kira-kira 3x; isi materi tetap terbaca dari audio dan teks layar.
                    ...($hasVideo ? ['media_resolution' => 'MEDIA_RESOLUTION_LOW'] : []),
                ],
            ];

            try {
                $response = Http::timeout(300)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, $payload);

                if ($response->successful()) {
                    return $response->json();
                }

                $errBody = $response->json();
                $errMsg = $errBody['error']['message'] ?? $response->body();
                $lastError = "Gemini API ({$modelName}) Error: ".$errMsg;
                Log::warning("Gemini model {$modelName} failed: ".$errMsg);

                // If error is invalid API key, stop trying other models
                if (str_contains($errMsg, 'API_KEY_INVALID') || str_contains($errMsg, 'API key not valid')) {
                    throw new Exception('API Key Gemini tidak valid. Silakan periksa kembali GEMINI_API_KEY Anda.');
                }
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'API Key Gemini tidak valid')) {
                    throw $e;
                }
                $lastError = $e->getMessage();
            }
        }

        throw new Exception($lastError ?: 'Gagal menghubungi layanan Google Gemini. Silakan coba beberapa saat lagi.');
    }

    /**
     * Parse and validate Gemini JSON response
     */
    protected function parseResponse(array $response, string $type): array
    {
        $rawText = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (! $rawText) {
            throw new Exception('Google Gemini tidak mengembalikan respons teks yang valid.');
        }

        // Clean markdown backticks if any
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
        if (! $decoded || ! isset($decoded['questions']) || ! is_array($decoded['questions'])) {
            // Check if returned directly as array
            if (is_array($decoded) && isset($decoded[0]['question'])) {
                $questionsList = $decoded;
            } else {
                Log::error('Failed to parse Gemini JSON: '.$cleanJson);
                throw new Exception('Format keluaran AI tidak dapat diproses sebagai daftar soal. Silakan coba lagi.');
            }
        } else {
            $questionsList = $decoded['questions'];
        }

        $sanitized = [];
        foreach ($questionsList as $i => $q) {
            $questionText = trim($q['question'] ?? '');
            if (empty($questionText)) {
                continue;
            }

            $correctAnswer = strtolower(trim($q['correct_answer'] ?? 'a'));
            if ($type === 'multiple_choice') {
                if (! in_array($correctAnswer, ['a', 'b', 'c', 'd', 'e'])) {
                    $correctAnswer = 'a';
                }
            }

            $sanitized[] = [
                'type' => $type,
                'question' => $questionText,
                'option_a' => ($type === 'multiple_choice') ? trim($q['option_a'] ?? '') : null,
                'option_b' => ($type === 'multiple_choice') ? trim($q['option_b'] ?? '') : null,
                'option_c' => ($type === 'multiple_choice') ? trim($q['option_c'] ?? '') : null,
                'option_d' => ($type === 'multiple_choice') ? trim($q['option_d'] ?? '') : null,
                'option_e' => ($type === 'multiple_choice') ? trim($q['option_e'] ?? '') : null,
                'option_f' => null,
                'correct_answer' => $correctAnswer,
                'explanation' => trim($q['explanation'] ?? ''),
            ];
        }

        if (empty($sanitized)) {
            throw new Exception('AI tidak menghasilkan butir soal yang valid dari dokumen tersebut.');
        }

        return $sanitized;
    }
}
