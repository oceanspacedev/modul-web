<?php

namespace App\Http\Controllers;

use App\Exports\TrainingQuestionTemplate;
use App\Http\Requests\ReviseAiQuestionRequest;
use App\Http\Requests\TrainingQuestionRequest;
use App\Jobs\AiTask;
use App\Jobs\GenerateExportFile;
use App\Jobs\GenerateTrainingQuestions;
use App\Jobs\ProcessQuestionImport;
use App\Jobs\ReviseTrainingQuestion;
use App\Models\Document;
use App\Models\Training;
use App\Models\TrainingQuestion;
use App\Support\QuizImage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Authorize('update', 'training')]
class TrainingQuestionController extends Controller
{
    /**
     * Show quiz questions management for a training
     */
    public function index(Training $training): View
    {
        $training->load(['questions', 'documents.versions']);
        $allDocuments = Document::with('dokumentype')->orderBy('name')->get();
        $isGeminiConfigured = ! empty(config('services.gemini.api_key'));

        return view('training.questions.index', [
            'title' => 'Kelola Kuis Pelatihan: '.$training->title,
            'active' => 'training',
            'training' => $training,
            'questions' => $training->questions,
            'documents' => $training->documents,
            'allDocuments' => $allDocuments,
            'isGeminiConfigured' => $isGeminiConfigured,
        ]);
    }

    /**
     * Download Excel question template
     */
    public function downloadTemplate(Training $training): BinaryFileResponse
    {
        $filename = 'template_soal_kuis_'.Str::slug($training->title).'.xlsx';

        return Excel::download(new TrainingQuestionTemplate, $filename);
    }

    /**
     * Export the question bank with answer keys (template format, re-importable) in the background;
     * the file arrives in the notification inbox.
     */
    public function export(Request $request, Training $training): RedirectResponse
    {
        GenerateExportFile::dispatch($request->user()->id, GenerateExportFile::TRAINING_QUESTIONS, $training->id);

        return back()->with('info', GenerateExportFile::QUEUED_MESSAGE);
    }

    /**
     * Import questions from an Excel/CSV file in the background (parsing a spreadsheet does not fit the web memory
     * limit for large files); the result arrives in the notification inbox.
     */
    public function importExcel(Request $request, Training $training): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->storeAs('imports', Str::uuid().'.'.$file->getClientOriginalExtension(), 'local');

        ProcessQuestionImport::dispatch($request->user()->id, ProcessQuestionImport::TRAINING, $training->id, $path);

        return back()->with('info', ProcessQuestionImport::QUEUED_MESSAGE);
    }

    /**
     * Import questions via Copy-Paste Plain Text
     */
    public function importText(Request $request, Training $training): RedirectResponse
    {

        $request->validate([
            'raw_text' => 'required|string|min:5',
        ]);

        try {
            $count = $this->parseTextQuestions($request->input('raw_text'), $training->id);
            if ($count === 0) {
                return back()->with('error', 'Tidak ada soal yang berhasil diparsing. Pastikan format penulisan sudah sesuai petunjuk.');
            }

            return back()->with('success', "Berhasil menambahkan {$count} soal kuis dari teks copy-paste!");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal memproses teks: '.$e->getMessage());
        }
    }

    /**
     * Helper to parse pasted raw text into questions
     */
    protected function parseTextQuestions(string $rawText, int $trainingId): int
    {
        // Normalize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $rawText);

        // Split by double newline or numbered list pattern
        $pattern = '/(?:\n\s*\n+)|(?<=\n)(?=(?:(?:Soal\s*)?\d+[\.\)]\s+))/i';
        $blocks = preg_split($pattern, trim($text));

        $imported = 0;

        foreach ($blocks as $blockIndex => $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $lines = array_values(array_filter(array_map('trim', explode("\n", $block))));
            if (empty($lines)) {
                continue;
            }

            $type = 'multiple_choice';
            $questionLines = [];
            $options = [];
            $key = null;
            $explanation = null;

            foreach ($lines as $line) {
                // Check TYPE
                if (preg_match('/^(?:TIPE|TYPE)\s*:\s*(.*)$/i', $line, $m)) {
                    $val = strtolower(trim($m[1]));
                    if (in_array($val, ['essay', 'esai', 'uraian', 'text'])) {
                        $type = 'essay';
                    }

                    continue;
                }

                // Check ANSWER KEY
                if (preg_match('/^(?:KUNCI|JAWABAN|ANSWER|KEY)\s*:\s*(.*)$/i', $line, $m)) {
                    $key = trim($m[1]);

                    continue;
                }

                // Check EXPLANATION / NOTES
                if (preg_match('/^(?:PENJELASAN|PEMBAHASAN|CATATAN|EXPLANATION|NOTE)\s*:\s*(.*)$/i', $line, $m)) {
                    $explanation = trim($m[1]);

                    continue;
                }

                // Check Options A-F
                if (preg_match('/^([A-Fa-f])[\.\)]\s*(.*)$/', $line, $m)) {
                    $optLetter = strtolower($m[1]);
                    $options[$optLetter] = trim($m[2]);

                    continue;
                }

                // Otherwise, it's question text line
                $questionLines[] = $line;
            }

            if (empty($questionLines)) {
                continue;
            }

            // Combine question text and strip leading numbering like "1. ", "1) ", "Soal 1: "
            $fullQuestion = implode(' ', $questionLines);
            $fullQuestion = preg_replace('/^(?:Soal\s*)?\d+[\.\)]\s*/i', '', $fullQuestion);
            $fullQuestion = trim($fullQuestion);

            if ($fullQuestion === '') {
                continue;
            }

            // If no option A or B found, treat as Essay automatically
            if (! isset($options['a']) || ! isset($options['b'])) {
                $type = 'essay';
            }

            if ($type === 'essay') {
                TrainingQuestion::create([
                    'training_id' => $trainingId,
                    'type' => 'essay',
                    'question' => $fullQuestion,
                    'option_a' => null,
                    'option_b' => null,
                    'option_c' => null,
                    'option_d' => null,
                    'option_e' => null,
                    'option_f' => null,
                    'correct_answer' => $key ?: null,
                    'explanation' => $explanation,
                ]);
                $imported++;
            } else {
                // Multiple Choice: resolve key letter
                $keyLetter = 'a';
                if ($key) {
                    $cleanedKey = strtolower(trim($key));
                    if (array_key_exists($cleanedKey, $options)) {
                        $keyLetter = $cleanedKey;
                    } else {
                        // Check if key matches full content of an option
                        foreach ($options as $l => $content) {
                            if (strcasecmp($content, $key) === 0) {
                                $keyLetter = $l;
                                break;
                            }
                        }
                    }
                }

                TrainingQuestion::create([
                    'training_id' => $trainingId,
                    'type' => 'multiple_choice',
                    'question' => $fullQuestion,
                    'option_a' => $options['a'] ?? null,
                    'option_b' => $options['b'] ?? null,
                    'option_c' => $options['c'] ?? null,
                    'option_d' => $options['d'] ?? null,
                    'option_e' => $options['e'] ?? null,
                    'option_f' => $options['f'] ?? null,
                    'correct_answer' => $keyLetter,
                    'explanation' => $explanation,
                ]);
                $imported++;
            }
        }

        return $imported;
    }

    /**
     * Store a new question for the training
     */
    public function store(TrainingQuestionRequest $request, Training $training): RedirectResponse
    {
        $training->questions()->create($request->questionDataWithImages());

        return back()->with('success', 'Soal kuis baru berhasil ditambahkan!');
    }

    /**
     * Update an existing question
     */
    public function update(TrainingQuestionRequest $request, Training $training, TrainingQuestion $question): RedirectResponse
    {
        $question->update($request->questionDataWithImages());

        return back()->with('success', 'Soal kuis berhasil diperbarui!');
    }

    /**
     * Delete a question
     */
    public function destroy(Training $training, TrainingQuestion $question): RedirectResponse
    {
        $question->delete();

        return back()->with('success', 'Soal kuis berhasil dihapus!');
    }

    /**
     * Delete all questions for a training
     */
    public function deleteAll(Training $training): RedirectResponse
    {
        // One by one so each question's images are removed from storage too.
        $training->questions()->get()->each->delete();

        return back()->with('success', 'Semua soal kuis berhasil dihapus!');
    }

    /**
     * Start AI question generation from the selected training documents on the AI queue: reading the documents and
     * waiting for Gemini is too heavy for a web request. The page polls aiStatus for the result.
     */
    public function generateAiQuestions(Request $request, Training $training): JsonResponse
    {
        $validated = $request->validate([
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'exists:documents,id',
            'question_count' => 'required|integer|min:1|max:100',
            'question_type' => 'required|in:multiple_choice,essay',
            'difficulty' => 'required|in:mudah,sedang,sulit,campuran',
            'custom_prompt' => 'nullable|string|max:1000',
            'api_key' => 'nullable|string|max:255',
        ]);

        if (! Document::whereIn('id', $validated['document_ids'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen yang dipilih tidak ditemukan.',
            ], 422);
        }

        if ($response = $this->geminiNotConfigured($validated['api_key'] ?? null)) {
            return $response;
        }

        // Also auto-attach these documents to training if not already attached
        $training->documents()->syncWithoutDetaching($validated['document_ids']);

        $token = AiTask::newToken();
        GenerateTrainingQuestions::dispatch(
            $token,
            array_map('intval', $validated['document_ids']),
            (int) $validated['question_count'],
            $validated['question_type'],
            $validated['difficulty'],
            $validated['custom_prompt'] ?? null,
            $validated['api_key'] ?? null,
        );

        return response()->json(['success' => true, 'token' => $token]);
    }

    /**
     * Result of generateAiQuestions / reviseAiQuestion, polled by the questions page.
     */
    public function aiStatus(Training $training, string $token): JsonResponse
    {
        $result = AiTask::status($token);

        if (! $result) {
            return response()->json(['status' => 'failed', 'message' => 'Proses AI tidak ditemukan atau sudah kedaluwarsa. Silakan ulangi.'], 404);
        }

        return response()->json($result);
    }

    /**
     * Rewrite one question with AI (question only, answers only, or everything), from the AI review step or from the
     * edit form of a saved question (question_id). The AI re-reads the documents and checks the new question against
     * the other review cards and the saved questions; it runs on the AI queue and the page polls aiStatus.
     */
    public function reviseAiQuestion(ReviseAiQuestionRequest $request, Training $training): JsonResponse
    {
        $validated = $request->validate([
            'question_id' => ['nullable', 'integer', Rule::exists('training_questions', 'id')->where('training_id', $training->id)],
            'document_ids' => 'required_without:question_id|array',
            'document_ids.*' => 'exists:documents,id',
        ], ['document_ids.required_without' => 'Pilih minimal 1 dokumen materi acuan.']);

        if ($response = $this->geminiNotConfigured($request->validated('api_key'))) {
            return $response;
        }

        $token = AiTask::newToken();
        ReviseTrainingQuestion::dispatch(
            $token,
            $training->id,
            // The edit form picks no documents: the job uses the training's own documents (none: question text only).
            array_map('intval', $validated['document_ids'] ?? []),
            $request->validated('question'),
            $request->validated('part'),
            $request->validated('instruction'),
            array_values(array_filter($request->validated('other_questions') ?? [], 'filled')),
            isset($validated['question_id']) ? (int) $validated['question_id'] : null,
            $request->validated('api_key'),
        );

        return response()->json(['success' => true, 'token' => $token]);
    }

    private function geminiNotConfigured(?string $apiKey): ?JsonResponse
    {
        if (filled($apiKey) || filled(config('services.gemini.api_key'))) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'GEMINI_API_KEY belum dikonfigurasi. Silakan masukkan API Key Gemini di pengaturan atau file .env!'], 422);
    }

    /**
     * Upload one image from the AI review step; the returned path is sent along when the questions are saved.
     */
    public function uploadImage(Request $request, Training $training): JsonResponse
    {
        $request->validate(['image' => ['required', ...QuizImage::fileRules()]]);

        $path = QuizImage::store($request->file('image'));

        return response()->json(['path' => $path, 'url' => QuizImage::url($path)]);
    }

    /**
     * Save AI-generated questions in batch after trainer review
     */
    public function saveAiBatch(Request $request, Training $training): JsonResponse|RedirectResponse
    {
        $request->validate([
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'nullable|string',
            'questions.*.type' => 'required|in:multiple_choice,essay',
            'questions.*.correct_answer' => 'required|string',
            'questions.*.question_image' => QuizImage::pathRules(),
            'questions.*.option_images' => 'nullable|array',
            'questions.*.option_images.*' => QuizImage::pathRules(),
        ]);

        foreach ($request->questions as $index => $q) {
            if (blank($q['question'] ?? null) && blank($q['question_image'] ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soal #'.($index + 1).': pertanyaan wajib diisi teks atau gambar.',
                ], 422);
            }
        }

        $savedCount = 0;
        foreach ($request->questions as $q) {
            $type = $q['type'] ?? 'multiple_choice';
            $training->questions()->create([
                'type' => $type,
                'question' => $q['question'] ?? '',
                'option_a' => ($type === 'multiple_choice') ? ($q['option_a'] ?? null) : null,
                'option_b' => ($type === 'multiple_choice') ? ($q['option_b'] ?? null) : null,
                'option_c' => ($type === 'multiple_choice') ? ($q['option_c'] ?? null) : null,
                'option_d' => ($type === 'multiple_choice') ? ($q['option_d'] ?? null) : null,
                'option_e' => ($type === 'multiple_choice') ? ($q['option_e'] ?? null) : null,
                'correct_answer' => $q['correct_answer'] ?? 'a',
                'explanation' => $q['explanation'] ?? null,
                ...QuizImage::fromPayload($q, $type === 'multiple_choice' ? ['a', 'b', 'c', 'd', 'e'] : []),
            ]);
            $savedCount++;
        }

        if ($request->ajax() || $request->wantsJson()) {
            // Pelatihan baru: lanjut ke pertanyaan pengaturan setelah soal tersimpan.
            $redirect = null;
            if ($training->needsSetup()) {
                session()->flash('success', "{$savedCount} soal AI tersimpan. Sekarang jawab beberapa pertanyaan pengaturan.");
                $redirect = route('training.setup', $training);
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil menyimpan {$savedCount} butir soal hasil AI ke kuis pelatihan!",
                'redirect' => $redirect,
            ]);
        }

        return back()->with('success', "Berhasil menyimpan {$savedCount} butir soal hasil AI!");
    }
}
