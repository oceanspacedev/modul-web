<?php

namespace App\Http\Controllers;

use App\Exports\LearningModuleQuestionTemplate;
use App\Http\Requests\LearningModuleQuestionRequest;
use App\Http\Requests\ReviseAiQuestionRequest;
use App\Imports\LearningModuleQuestionImport;
use App\Jobs\GenerateExportFile;
use App\Jobs\GenerateLearningModuleQuestions;
use App\Jobs\ReviseLearningModuleQuestion;
use App\Models\LearningModule;
use App\Models\LearningModuleItem;
use App\Models\LearningModuleQuestion;
use App\Services\MaterialQuizService;
use App\Support\QuizImage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Authorize('update', 'module')]
class LearningModuleQuestionController extends Controller
{
    public function __construct(private MaterialQuizService $materialQuiz) {}

    public function index(LearningModule $module): View
    {
        $module->load(['questions', 'items.materialable']);

        return view('learning_module.questions', [
            'title' => 'Soal Modul: '.$module->title,
            'active' => $module->menuKey('learning-module'),
            'module' => $module,
            'questions' => $module->questions,
            'aiItems' => $module->availableItems()->filter(fn (LearningModuleItem $item) => GenerateLearningModuleQuestions::supports($item)),
            'unsupportedAiItems' => $module->availableItems()->reject(fn (LearningModuleItem $item) => GenerateLearningModuleQuestions::supports($item)),
            'isGeminiConfigured' => ! empty(config('services.gemini.api_key')),
        ]);
    }

    public function store(LearningModuleQuestionRequest $request, LearningModule $module): RedirectResponse
    {
        $module->questions()->create([...$request->questionDataWithImages(), 'sort_order' => $this->nextSortOrder($module)]);
        $this->materialQuiz->refresh($module);

        return back()->with('success', 'Soal baru berhasil ditambahkan!');
    }

    public function update(LearningModuleQuestionRequest $request, LearningModule $module, LearningModuleQuestion $question): RedirectResponse
    {
        $question->update($request->questionDataWithImages());

        return back()->with('success', 'Soal berhasil diperbarui!');
    }

    public function destroy(LearningModule $module, LearningModuleQuestion $question): RedirectResponse
    {
        $question->delete();
        $this->materialQuiz->refresh($module);

        return back()->with('success', 'Soal berhasil dihapus!');
    }

    public function deleteAll(LearningModule $module): RedirectResponse
    {
        // Satu per satu supaya gambar soal ikut terhapus dari storage.
        $module->questions()->get()->each->delete();
        $this->materialQuiz->refresh($module);

        return back()->with('success', 'Semua soal berhasil dihapus!');
    }

    public function downloadTemplate(LearningModule $module): BinaryFileResponse
    {
        return Excel::download(new LearningModuleQuestionTemplate, 'template_soal_modul_'.Str::slug($module->title).'.xlsx');
    }

    /**
     * Bank soal beserta kunci jawaban (format template, bisa diimpor ulang ke modul lain), dibuat di belakang layar;
     * filenya dikirim lewat menu Notifikasi.
     */
    public function export(Request $request, LearningModule $module): RedirectResponse
    {
        GenerateExportFile::dispatch($request->user()->id, GenerateExportFile::LEARNING_MODULE_QUESTIONS, $module->id);

        return back()->with('info', GenerateExportFile::QUEUED_MESSAGE);
    }

    public function importExcel(Request $request, LearningModule $module): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new LearningModuleQuestionImport($module, $this->nextSortOrder($module));
            DB::transaction(fn () => Excel::import($import, $request->file('file')));
            $this->materialQuiz->refresh($module);

            return back()->with('success', "Berhasil mengimpor {$import->getImportedCount()} soal dari file Excel!");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal impor file: '.$e->getMessage());
        }
    }

    /**
     * Mulai pembuatan soal AI di queue; video bisa butuh beberapa menit. Hasilnya diambil lewat aiStatus.
     */
    public function generateAi(Request $request, LearningModule $module): JsonResponse
    {
        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => ['integer', Rule::exists('learning_module_items', 'id')->where('learning_module_id', $module->id)],
            'question_count' => 'required|integer|min:1|max:100',
            'difficulty' => 'required|in:mudah,sedang,sulit,campuran',
            'custom_prompt' => 'nullable|string|max:1000',
        ]);

        if (empty(config('services.gemini.api_key'))) {
            return response()->json(['success' => false, 'message' => 'GEMINI_API_KEY belum dikonfigurasi.'], 422);
        }

        $token = (string) Str::uuid();
        Cache::put(GenerateLearningModuleQuestions::cacheKey($token), ['status' => 'pending'], now()->addHours(2));

        GenerateLearningModuleQuestions::dispatch(
            $token,
            $module->id,
            $validated['item_ids'],
            (int) $validated['question_count'],
            $validated['difficulty'],
            $validated['custom_prompt'] ?? null,
        );

        return response()->json(['success' => true, 'token' => $token]);
    }

    public function aiStatus(LearningModule $module, string $token): JsonResponse
    {
        $result = Cache::get(GenerateLearningModuleQuestions::cacheKey($token));

        if (! $result) {
            return response()->json(['status' => 'failed', 'message' => 'Proses AI tidak ditemukan atau sudah kedaluwarsa. Silakan ulangi.'], 404);
        }

        return response()->json($result);
    }

    /**
     * Ubah satu soal di langkah review AI (pertanyaan saja, jawaban saja, atau semuanya). AI membaca ulang materi yang
     * dipilih dan mengecek pertanyaannya belum ada, di queue seperti generateAi; hasilnya diambil lewat aiStatus.
     */
    public function reviseAi(ReviseAiQuestionRequest $request, LearningModule $module): JsonResponse
    {
        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => ['integer', Rule::exists('learning_module_items', 'id')->where('learning_module_id', $module->id)],
        ], ['item_ids.required' => 'Pilih minimal satu materi acuan.']);

        if (empty(config('services.gemini.api_key'))) {
            return response()->json(['success' => false, 'message' => 'GEMINI_API_KEY belum dikonfigurasi.'], 422);
        }

        $token = (string) Str::uuid();
        Cache::put(GenerateLearningModuleQuestions::cacheKey($token), ['status' => 'pending'], now()->addHours(2));

        ReviseLearningModuleQuestion::dispatch(
            $token,
            $module->id,
            $validated['item_ids'],
            $request->validated('question'),
            $request->validated('part'),
            $request->validated('instruction'),
            array_filter($request->validated('other_questions') ?? [], 'filled'),
        );

        return response()->json(['success' => true, 'token' => $token]);
    }

    /**
     * Unggah satu gambar dari langkah review soal AI; path-nya dikirim lagi saat soal disimpan.
     */
    public function uploadImage(Request $request, LearningModule $module): JsonResponse
    {
        $request->validate(['image' => ['required', ...QuizImage::fileRules()]]);

        $path = QuizImage::store($request->file('image'));

        return response()->json(['path' => $path, 'url' => QuizImage::url($path)]);
    }

    /**
     * Simpan soal AI setelah ditinjau trainer.
     */
    public function saveAiBatch(Request $request, LearningModule $module): JsonResponse
    {
        $request->validate([
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'nullable|string',
            'questions.*.option_a' => 'nullable|string',
            'questions.*.option_b' => 'nullable|string',
            'questions.*.option_c' => 'nullable|string',
            'questions.*.option_d' => 'nullable|string',
            'questions.*.option_e' => 'nullable|string',
            'questions.*.correct_answer' => 'required|in:a,b,c,d,e',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.question_image' => QuizImage::pathRules(),
            'questions.*.option_images' => 'nullable|array',
            'questions.*.option_images.*' => QuizImage::pathRules(),
        ]);

        // Pertanyaan dan pilihan terisi = punya teks atau gambar.
        foreach ($request->questions as $index => $q) {
            if (blank($q['question'] ?? null) && blank($q['question_image'] ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soal #'.($index + 1).': pertanyaan wajib diisi teks atau gambar.',
                ], 422);
            }

            $filled = fn (string $letter) => filled($q['option_'.$letter] ?? null) || filled($q['option_images'][$letter] ?? null);

            foreach (['a', 'b'] as $letter) {
                if (! $filled($letter)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Soal #'.($index + 1).': pilihan '.strtoupper($letter).' wajib diisi teks atau gambar.',
                    ], 422);
                }
            }

            if (! $filled($q['correct_answer'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Soal #'.($index + 1).': kunci jawaban menunjuk pilihan '.strtoupper($q['correct_answer']).' yang kosong.',
                ], 422);
            }
        }

        $sortOrder = $this->nextSortOrder($module);
        DB::transaction(function () use ($request, $module, &$sortOrder) {
            foreach ($request->questions as $q) {
                $module->questions()->create([
                    'question' => $q['question'] ?? '',
                    'option_a' => $q['option_a'] ?? null,
                    'option_b' => $q['option_b'] ?? null,
                    'option_c' => $q['option_c'] ?? null,
                    'option_d' => $q['option_d'] ?? null,
                    'option_e' => $q['option_e'] ?? null,
                    'correct_answer' => $q['correct_answer'],
                    'explanation' => $q['explanation'] ?? null,
                    ...QuizImage::fromPayload($q, LearningModuleQuestion::OPTION_LETTERS),
                    'sort_order' => $sortOrder++,
                ]);
            }
        });

        $this->materialQuiz->refresh($module);
        $count = count($request->questions);
        $message = "Berhasil menyimpan {$count} soal hasil AI ke modul!";

        // Video/document quizzes go straight back to the material; regular modules stay on their question list.
        $redirect = $module->isOwned() ? $module->managementUrl() : $module->url('questions.index');
        session()->flash('success', $message);

        return response()->json([
            'success' => true,
            'message' => $message,
            'redirect' => $redirect,
        ]);
    }

    private function nextSortOrder(LearningModule $module): int
    {
        return (int) LearningModuleQuestion::where('learning_module_id', $module->id)->max('sort_order') + 1;
    }
}
