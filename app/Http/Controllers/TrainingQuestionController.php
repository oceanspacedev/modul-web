<?php

namespace App\Http\Controllers;

use App\Exports\TrainingQuestionTemplate;
use App\Imports\TrainingQuestionImport;
use App\Models\Document;
use App\Models\Training;
use App\Models\TrainingQuestion;
use App\Services\GeminiQuestionService;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TrainingQuestionController extends Controller
{
    /**
     * Show quiz questions management for a training
     */
    public function index($trainingId)
    {
        $training = Training::with(['questions', 'documents.versions'])->findOrFail($trainingId);
        $allDocuments = Document::with('dokumentype')->orderBy('name')->get();
        $isGeminiConfigured = !empty(config('services.gemini.api_key', env('GEMINI_API_KEY')));

        return view('training.questions.index', [
            'title' => 'Kelola Kuis Pelatihan: ' . $training->title,
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
    public function downloadTemplate($trainingId)
    {
        $training = Training::findOrFail($trainingId);
        $filename = 'template_soal_kuis_' . \Str::slug($training->title) . '.xlsx';
        return Excel::download(new TrainingQuestionTemplate, $filename);
    }

    /**
     * Import questions from Excel/CSV file
     */
    public function importExcel(Request $request, $trainingId)
    {
        $training = Training::findOrFail($trainingId);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new TrainingQuestionImport($training->id);
            Excel::import($import, $request->file('file'));

            $count = $import->getImportedCount();
            return back()->with('success', "Berhasil mengimpor {$count} soal kuis dari file Excel!");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal impor file: ' . $e->getMessage());
        }
    }

    /**
     * Import questions via Copy-Paste Plain Text
     */
    public function importText(Request $request, $trainingId)
    {
        $training = Training::findOrFail($trainingId);

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
            return back()->with('error', 'Gagal memproses teks: ' . $e->getMessage());
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
            if (!isset($options['a']) || !isset($options['b'])) {
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
    public function store(Request $request, $trainingId)
    {
        $training = Training::findOrFail($trainingId);
        $type = $request->input('type', 'multiple_choice');

        if ($type === 'essay') {
            $validated = $request->validate([
                'type' => 'required|in:essay',
                'question' => 'required|string',
                'correct_answer' => 'nullable|string',
                'explanation' => 'nullable|string',
            ]);

            $validated['option_a'] = null;
            $validated['option_b'] = null;
            $validated['option_c'] = null;
            $validated['option_d'] = null;
            $validated['option_e'] = null;
            $validated['option_f'] = null;
        } else {
            $validated = $request->validate([
                'type' => 'nullable|in:multiple_choice',
                'question' => 'required|string',
                'option_a' => 'required|string',
                'option_b' => 'required|string',
                'option_c' => 'nullable|string',
                'option_d' => 'nullable|string',
                'option_e' => 'nullable|string',
                'option_f' => 'nullable|string',
                'correct_answer' => 'required|in:a,b,c,d,e,f',
                'explanation' => 'nullable|string',
            ]);
            $validated['type'] = 'multiple_choice';
        }

        $training->questions()->create($validated);

        return back()->with('success', 'Soal kuis baru berhasil ditambahkan!');
    }

    /**
     * Update an existing question
     */
    public function update(Request $request, $trainingId, $questionId)
    {
        $question = TrainingQuestion::where('training_id', $trainingId)->findOrFail($questionId);
        $type = $request->input('type', 'multiple_choice');

        if ($type === 'essay') {
            $validated = $request->validate([
                'type' => 'required|in:essay',
                'question' => 'required|string',
                'correct_answer' => 'nullable|string',
                'explanation' => 'nullable|string',
            ]);

            $validated['option_a'] = null;
            $validated['option_b'] = null;
            $validated['option_c'] = null;
            $validated['option_d'] = null;
            $validated['option_e'] = null;
            $validated['option_f'] = null;
        } else {
            $validated = $request->validate([
                'type' => 'nullable|in:multiple_choice',
                'question' => 'required|string',
                'option_a' => 'required|string',
                'option_b' => 'required|string',
                'option_c' => 'nullable|string',
                'option_d' => 'nullable|string',
                'option_e' => 'nullable|string',
                'option_f' => 'nullable|string',
                'correct_answer' => 'required|in:a,b,c,d,e,f',
                'explanation' => 'nullable|string',
            ]);
            $validated['type'] = 'multiple_choice';
        }

        $question->update($validated);

        return back()->with('success', 'Soal kuis berhasil diperbarui!');
    }

    /**
     * Delete a question
     */
    public function destroy($trainingId, $questionId)
    {
        $question = TrainingQuestion::where('training_id', $trainingId)->findOrFail($questionId);
        $question->delete();

        return back()->with('success', 'Soal kuis berhasil dihapus!');
    }

    /**
     * Delete all questions for a training
     */
    public function deleteAll($trainingId)
    {
        $training = Training::findOrFail($trainingId);
        $training->questions()->delete();

        return back()->with('success', 'Semua soal kuis berhasil dihapus!');
    }

    /**
     * Generate questions using Google Gemini AI from selected training documents
     */
    public function generateAiQuestions(Request $request, $trainingId)
    {
        $training = Training::findOrFail($trainingId);

        $request->validate([
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'exists:documents,id',
            'question_count' => 'required|integer|min:1|max:100',
            'question_type' => 'required|in:multiple_choice,essay',
            'difficulty' => 'required|in:mudah,sedang,sulit,campuran',
            'custom_prompt' => 'nullable|string|max:1000',
            'api_key' => 'nullable|string|max:255',
        ]);

        try {
            $documents = Document::with('versions')->whereIn('id', $request->document_ids)->get();

            if ($documents->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen yang dipilih tidak ditemukan.',
                ], 422);
            }

            // Also auto-attach these documents to training if not already attached
            $training->documents()->syncWithoutDetaching($request->document_ids);

            $geminiService = new GeminiQuestionService($request->api_key);
            $questions = $geminiService->generateQuestions(
                $documents->all(),
                (int) $request->question_count,
                $request->question_type,
                $request->difficulty,
                $request->custom_prompt
            );

            return response()->json([
                'success' => true,
                'count' => count($questions),
                'questions' => $questions,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Save AI-generated questions in batch after trainer review
     */
    public function saveAiBatch(Request $request, $trainingId)
    {
        $training = Training::findOrFail($trainingId);

        $request->validate([
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'required|string',
            'questions.*.type' => 'required|in:multiple_choice,essay',
            'questions.*.correct_answer' => 'required|string',
        ]);

        $savedCount = 0;
        foreach ($request->questions as $q) {
            $type = $q['type'] ?? 'multiple_choice';
            TrainingQuestion::create([
                'training_id' => $training->id,
                'type' => $type,
                'question' => $q['question'],
                'option_a' => ($type === 'multiple_choice') ? ($q['option_a'] ?? null) : null,
                'option_b' => ($type === 'multiple_choice') ? ($q['option_b'] ?? null) : null,
                'option_c' => ($type === 'multiple_choice') ? ($q['option_c'] ?? null) : null,
                'option_d' => ($type === 'multiple_choice') ? ($q['option_d'] ?? null) : null,
                'option_e' => ($type === 'multiple_choice') ? ($q['option_e'] ?? null) : null,
                'correct_answer' => $q['correct_answer'] ?? 'a',
                'explanation' => $q['explanation'] ?? null,
            ]);
            $savedCount++;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menyimpan {$savedCount} butir soal hasil AI ke kuis pelatihan!",
            ]);
        }

        return back()->with('success', "Berhasil menyimpan {$savedCount} butir soal hasil AI!");
    }
}
