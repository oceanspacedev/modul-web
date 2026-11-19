<?php

namespace App\Http\Controllers;

use App\Models\Training;
use App\Models\TrainingEvaluationQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Kelola pertanyaan form evaluasi sebuah pelatihan: tambah, ubah, hapus, dan urutkan.
 */
#[Authorize('update', 'training')]
class TrainingEvaluationQuestionController extends Controller
{
    /** Batas panjang isian "Pilihan jawaban" secara keseluruhan (semua baris), longgar untuk MAX_OPTIONS opsi. */
    private const OPTIONS_TEXT_MAX = 50000;

    /** Pelatihan sumber yang ditawarkan di dialog salin, terbaru dulu. */
    private const COPY_SOURCES_SHOWN = 200;

    public function index(Training $training): View
    {
        $training->load('evaluationQuestions');

        $copySources = Training::query()
            ->whereKeyNot($training->id)
            ->whereHas('evaluationQuestions')
            ->withCount('evaluationQuestions')
            ->latest('training_date')
            ->latest('id')
            ->limit(self::COPY_SOURCES_SHOWN)
            ->get(['id', 'title', 'training_date']);

        return view('training.evaluation.index', [
            'title' => 'Pertanyaan Evaluasi: '.$training->title,
            'active' => 'training',
            'training' => $training,
            'questions' => $training->evaluationQuestions,
            'sectionNames' => $training->evaluationQuestions->pluck('section')->filter(fn ($section) => filled($section))->unique(strict: true)->values(),
            'answerCount' => $training->evaluations()->count(),
            'copySources' => $copySources,
        ]);
    }

    public function store(Request $request, Training $training): RedirectResponse
    {
        $training->evaluationQuestions()->create([
            ...$this->questionData($request),
            'position' => (int) $training->evaluationQuestions()->max('position') + 1,
        ]);

        return back()->with('success', 'Pertanyaan evaluasi ditambahkan.');
    }

    public function update(Request $request, Training $training, TrainingEvaluationQuestion $evaluationQuestion): RedirectResponse
    {
        $data = $this->questionData($request);

        // Jawaban yang sudah masuk disimpan sesuai tipe lamanya, jadi tipe tidak boleh berganti setelah ada jawaban.
        if ($data['type'] !== $evaluationQuestion->type && $training->evaluations()->exists()) {
            throw ValidationException::withMessages([
                'type' => 'Tipe tidak bisa diubah karena sudah ada peserta yang mengisi. Hapus pertanyaan ini lalu buat yang baru.',
            ]);
        }

        $evaluationQuestion->update($data);

        return back()->with('success', 'Pertanyaan evaluasi diperbarui.');
    }

    /**
     * Geser pertanyaan satu langkah ke atas/bawah dengan menukar urutannya dengan tetangganya.
     */
    public function move(Request $request, Training $training, TrainingEvaluationQuestion $evaluationQuestion): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $ids = $training->evaluationQuestions()->pluck('id')->all();
        $index = array_search($evaluationQuestion->id, $ids, true);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($ids[$swap])) {
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
            foreach ($ids as $position => $id) {
                TrainingEvaluationQuestion::whereKey($id)->update(['position' => $position + 1]);
            }
        }

        return back();
    }

    public function destroy(Training $training, TrainingEvaluationQuestion $evaluationQuestion): RedirectResponse
    {
        $evaluationQuestion->delete();

        return back()->with('success', 'Pertanyaan evaluasi dihapus.');
    }

    /**
     * Isi form yang masih kosong dengan template bawaan.
     */
    public function addDefaults(Training $training): RedirectResponse
    {
        if ($training->evaluationQuestions()->exists()) {
            return back()->with('error', 'Template bawaan hanya bisa dipakai saat belum ada pertanyaan.');
        }

        $training->addDefaultEvaluationQuestions();

        return back()->with('success', 'Pertanyaan bawaan ditambahkan. Silakan sesuaikan.');
    }

    /**
     * Salin pertanyaan evaluasi dari pelatihan lain supaya form yang sama tidak perlu diketik ulang.
     * "replace" mengganti seluruh pertanyaan (hanya selama belum ada yang mengisi), "append" menambahkannya di akhir.
     */
    public function copyFrom(Request $request, Training $training): RedirectResponse
    {
        $validated = $request->validate([
            'source_training_id' => ['required', 'integer', Rule::exists('trainings', 'id'), Rule::notIn([$training->id])],
            'mode' => ['required', Rule::in(['replace', 'append'])],
        ], [
            'source_training_id.required' => 'Pilih pelatihan sumber.',
            'source_training_id.exists' => 'Pelatihan sumber tidak ditemukan.',
            'source_training_id.not_in' => 'Pilih pelatihan lain sebagai sumber.',
        ]);

        $source = Training::with('evaluationQuestions')->findOrFail($validated['source_training_id']);

        if ($source->evaluationQuestions->isEmpty()) {
            return back()->with('error', 'Pelatihan sumber belum punya pertanyaan evaluasi.');
        }

        $replace = $validated['mode'] === 'replace';
        if ($replace && $training->evaluations()->exists()) {
            return back()->with('error', 'Pertanyaan tidak bisa diganti karena sudah ada peserta yang mengisi. Pilih "Tambahkan di akhir".');
        }

        DB::transaction(function () use ($training, $source, $replace) {
            if ($replace) {
                $training->evaluationQuestions()->delete();
            }

            $position = $replace ? 0 : (int) $training->evaluationQuestions()->max('position');

            foreach ($source->evaluationQuestions as $question) {
                $training->evaluationQuestions()->create([
                    ...$question->only(['section', 'type', 'question', 'low_label', 'high_label', 'options', 'is_required']),
                    'position' => ++$position,
                ]);
            }
        });

        return back()->with('success', $source->evaluationQuestions->count().' pertanyaan disalin dari "'.$source->title.'".');
    }

    /**
     * Kolom pertanyaan dari form tambah/ubah. Label skala hanya disimpan untuk skala, opsi hanya untuk pilihan.
     *
     * @return array<string, mixed>
     */
    private function questionData(Request $request): array
    {
        $validated = $request->validate([
            'section' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(TrainingEvaluationQuestion::TYPES))],
            'question' => ['required', 'string', 'max:500'],
            'low_label' => ['nullable', 'string', 'max:60'],
            'high_label' => ['nullable', 'string', 'max:60'],
            'options' => ['nullable', 'string', 'max:'.self::OPTIONS_TEXT_MAX],
            'is_required' => ['nullable', 'boolean'],
        ], [
            'question.required' => 'Tulis pertanyaannya.',
        ]);

        $type = $validated['type'];
        $options = null;

        if ($type === TrainingEvaluationQuestion::TYPE_CHOICE) {
            // Satu opsi per baris; baris kosong dan duplikat persis dibuang. Tiap baris dirapikan dengan cara yang
            // sama seperti kiriman peserta (termasuk spasi tak terlihat dari hasil tempel), supaya opsi yang tersimpan
            // selalu cocok dengan nilai yang dikirim saat peserta memilihnya.
            $options = collect(preg_split('/\R/u', (string) ($validated['options'] ?? '')))
                ->map(fn (string $option) => Str::trim($option))
                ->filter(fn (string $option) => $option !== '')
                ->unique(strict: true)->values()->all();

            if (count($options) < 2 || count($options) > TrainingEvaluationQuestion::MAX_OPTIONS) {
                throw ValidationException::withMessages([
                    'options' => 'Tulis 2 sampai '.TrainingEvaluationQuestion::MAX_OPTIONS.' pilihan, satu per baris.',
                ]);
            }
        }

        $isScale = $type === TrainingEvaluationQuestion::TYPE_SCALE;

        return [
            'section' => filled($validated['section'] ?? null) ? trim($validated['section']) : null,
            'type' => $type,
            'question' => trim($validated['question']),
            'low_label' => $isScale ? ($validated['low_label'] ?? null) : null,
            'high_label' => $isScale ? ($validated['high_label'] ?? null) : null,
            'options' => $options,
            'is_required' => $request->boolean('is_required'),
        ];
    }
}
