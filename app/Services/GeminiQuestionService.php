<?php

namespace App\Services;

use App\Models\Document;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class GeminiQuestionService
{
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
     * Generate quiz questions from one or more documents
     *
     * @param  array  $documents  List of Document models
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

        // Attach documents to prompt parts
        foreach ($documents as $doc) {
            $filePath = $this->resolveDocumentPath($doc);
            if (! $filePath || ! file_exists($filePath)) {
                continue;
            }

            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $docPart = $this->buildFilePart($filePath, $ext, $doc->name);
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
        $response = $this->callGeminiApi($parts);

        return $this->parseResponse($response, $type);
    }

    /**
     * Resolve absolute file path for a Document
     */
    protected function resolveDocumentPath(Document $doc): ?string
    {
        // Try latest version first
        $latestVersion = $doc->versions()->first();
        if ($latestVersion && ! empty($latestVersion->path)) {
            $path = storage_path('app/public/dokumen/'.$latestVersion->path);
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try document direct path
        if (! empty($doc->path)) {
            $path = storage_path('app/public/dokumen/'.$doc->path);
            if (file_exists($path)) {
                return $path;
            }
            $publicPath = public_path('storage/dokumen/'.$doc->path);
            if (file_exists($publicPath)) {
                return $publicPath;
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
        $prompt .= "Analisis dokumen yang dilampirkan secara menyeluruh. Buatkan hingga {$count} butir {$typeDesc} berdasarkan materi dan fakta dalam dokumen tersebut.\n";
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
        $prompt .= "1. Soal HARUS sepenuhnya berbasis pada isi dan fakta dalam dokumen materi.\n";
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
    protected function callGeminiApi(array $parts): array
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
