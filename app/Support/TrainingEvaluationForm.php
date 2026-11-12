<?php

namespace App\Support;

use App\Models\TrainingEvaluation;
use App\Models\TrainingEvaluationQuestion;
use Closure;
use Illuminate\Support\Collection;

/**
 * Form evaluasi pelatihan. Pertanyaannya milik tiap pelatihan (TrainingEvaluationQuestion); kelas ini menyediakan
 * template bawaan, validasi kiriman peserta, dan rekap jawaban. Identitas peserta (nama, jabatan, divisi) serta
 * tanggal dan materi tidak pernah ditanyakan karena sudah ada di sistem.
 */
final class TrainingEvaluationForm
{
    public const SCALE_MIN = 1;

    public const SCALE_MAX = 5;

    public const TEXT_MAX = 1000;

    private const AGREE = ['Sangat Tidak Setuju', 'Sangat Setuju'];

    /** Template bawaan: [bagian, pertanyaan, label nilai terendah, label nilai tertinggi]. */
    private const DEFAULT_SCALES = [
        ['Pemateri', 'Instruksi yang diberikan pemateri jelas dan mudah dipahami', ...self::AGREE],
        ['Pemateri', 'Pemateri memiliki pemahaman yang baik tentang materi yang disampaikan', ...self::AGREE],
        ['Pemateri', 'Pemateri dapat mendorong peserta untuk aktif berpartisipasi selama sesi', ...self::AGREE],
        ['Pemateri', 'Secara keseluruhan, seberapa puas Anda dengan pemateri hari ini', 'Sangat Tidak Puas', 'Sangat Puas'],
        ['Materi & Pelatihan', 'Materi yang diberikan sangat membantu saya di lapangan/pekerjaan sehari-hari', ...self::AGREE],
        ['Materi & Pelatihan', 'Pelatihan ini memenuhi harapan saya', ...self::AGREE],
        ['Materi & Pelatihan', 'Sesi pelatihan terorganisir dengan baik', ...self::AGREE],
        ['Materi & Pelatihan', 'Durasi atau waktu pelatihan sudah sesuai dan cukup', ...self::AGREE],
        ['Materi & Pelatihan', 'Bagaimana Anda menilai pelatihan ini secara keseluruhan', 'Sangat Buruk', 'Sangat Baik'],
    ];

    private const DEFAULT_COMMENT = 'Ada tanggapan atau masukan untuk keseluruhan sesi ini?';

    /**
     * Pertanyaan bawaan sebagai baris training_evaluation_questions (tanpa training_id dan position): sembilan skala
     * dan satu masukan opsional. Disalin ke pelatihan saat form evaluasinya pertama kali dinyalakan.
     *
     * @return list<array{section: ?string, type: string, question: string, low_label: ?string, high_label: ?string, is_required: bool}>
     */
    public static function defaultQuestions(): array
    {
        $questions = array_map(fn (array $scale) => [
            'section' => $scale[0],
            'type' => TrainingEvaluationQuestion::TYPE_SCALE,
            'question' => $scale[1],
            'low_label' => $scale[2],
            'high_label' => $scale[3],
            'is_required' => true,
        ], self::DEFAULT_SCALES);

        $questions[] = [
            'section' => 'Masukan',
            'type' => TrainingEvaluationQuestion::TYPE_TEXT,
            'question' => self::DEFAULT_COMMENT,
            'low_label' => null,
            'high_label' => null,
            'is_required' => false,
        ];

        return $questions;
    }

    /**
     * @return list<int>
     */
    public static function scale(): array
    {
        return range(self::SCALE_MIN, self::SCALE_MAX);
    }

    /**
     * Pertanyaan berurutan dikelompokkan per bagian untuk ditampilkan: [['title' => ?string, 'questions' => [...]]].
     * Pertanyaan bersebelahan dengan bagian yang sama menjadi satu kelompok.
     *
     * @param  Collection<int, TrainingEvaluationQuestion>  $questions
     * @return list<array{title: ?string, questions: list<TrainingEvaluationQuestion>}>
     */
    public static function sections(Collection $questions): array
    {
        $sections = [];
        foreach ($questions as $question) {
            $last = array_key_last($sections);
            if ($last !== null && $sections[$last]['title'] === $question->section) {
                $sections[$last]['questions'][] = $question;
            } else {
                $sections[] = ['title' => $question->section, 'questions' => [$question]];
            }
        }

        return $sections;
    }

    /**
     * Aturan validasi kiriman peserta (answers[id pertanyaan]) sesuai tipe dan wajib/tidaknya tiap pertanyaan.
     *
     * @param  Collection<int, TrainingEvaluationQuestion>  $questions
     * @return array<string, mixed>
     */
    public static function rules(Collection $questions): array
    {
        $rules = ['answers' => [$questions->contains('is_required', true) ? 'required' : 'nullable', 'array']];

        foreach ($questions as $question) {
            $rules['answers.'.$question->id] = [
                $question->is_required ? 'required' : 'nullable',
                ...match ($question->type) {
                    TrainingEvaluationQuestion::TYPE_SCALE => ['integer', 'between:'.self::SCALE_MIN.','.self::SCALE_MAX],
                    TrainingEvaluationQuestion::TYPE_CHOICE => ['string', self::oneOf(array_map('strval', $question->options ?? []))],
                    default => ['string', self::withinTextLimit()],
                },
            ];
        }

        return $rules;
    }

    /**
     * Jawaban harus persis salah satu opsi. Dibandingkan langsung, bukan lewat aturan "in:...", supaya opsi berisi
     * tanda kutip, koma, atau garis miring terbalik tetap bisa dipilih.
     *
     * @param  list<string>  $options
     */
    private static function oneOf(array $options): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($options): void {
            // Kiriman bukan teks sudah ditolak aturan "string".
            if (is_scalar($value) && ! in_array((string) $value, $options, true)) {
                $fail('Pilihan jawaban tidak dikenal.');
            }
        };
    }

    /**
     * Batas panjang jawaban teks dihitung seperti di browser: satu baris baru = satu karakter (browser mengirimnya
     * sebagai dua karakter CR+LF).
     */
    private static function withinTextLimit(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_scalar($value) && mb_strlen(self::normalizeLineBreaks((string) $value)) > self::TEXT_MAX) {
                $fail('Jawaban maksimal '.self::TEXT_MAX.' karakter.');
            }
        };
    }

    private static function normalizeLineBreaks(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'answers.required' => 'Jawab setiap pertanyaan yang wajib diisi.',
            'answers.*.required' => 'Masih ada pertanyaan wajib yang belum dijawab.',
            'answers.*.between' => 'Nilai harus antara '.self::SCALE_MIN.' dan '.self::SCALE_MAX.'.',
            'answers.*.integer' => 'Nilai harus berupa angka '.self::SCALE_MIN.' sampai '.self::SCALE_MAX.'.',
            'answers.*.string' => 'Jawaban tidak valid.',
        ];
    }

    /**
     * Jawaban tervalidasi yang siap disimpan: hanya pertanyaan pelatihan ini, skala sebagai angka, teks dirapikan,
     * jawaban kosong dibuang.
     *
     * @param  Collection<int, TrainingEvaluationQuestion>  $questions
     * @param  array<int|string, mixed>  $input
     * @return array<int, int|string>
     */
    public static function answersFromInput(Collection $questions, array $input): array
    {
        $answers = [];
        foreach ($questions as $question) {
            $value = $input[$question->id] ?? null;
            $value = $question->isScale()
                ? ($value === null || $value === '' ? null : (int) $value)
                : trim(self::normalizeLineBreaks((string) $value));

            if ($value !== null && $value !== '') {
                $answers[$question->id] = $value;
            }
        }

        return $answers;
    }

    /**
     * Rekap jawaban untuk halaman detail pelatihan. Tiap pertanyaan membawa hasil sesuai tipenya: rata-rata (skala),
     * jumlah per opsi (pilihan, sebagai daftar label/count), atau daftar jawaban terbaru (teks, paling banyak $textLimit).
     *
     * @param  Collection<int, TrainingEvaluationQuestion>  $questions
     * @param  Collection<int, TrainingEvaluation>  $evaluations  terbaru dulu, dengan relasi user
     * @return array{count: int, average: float|null, questions: list<array<string, mixed>>}
     */
    public static function summarize(Collection $questions, Collection $evaluations, int $textLimit = 20): array
    {
        $rows = [];
        foreach ($questions as $question) {
            $answered = $evaluations->filter(fn (TrainingEvaluation $evaluation) => $evaluation->answerTo($question) !== null)->values();
            $values = $answered->map(fn (TrainingEvaluation $evaluation) => $evaluation->answerTo($question));
            $row = ['question' => $question, 'answered' => $answered->count()];

            if ($question->isScale()) {
                $row['average'] = $values->isEmpty() ? null : round($values->avg(), 2);
            } elseif ($question->type === TrainingEvaluationQuestion::TYPE_CHOICE) {
                // Opsi saat ini dulu (termasuk yang belum dipilih), lalu jawaban lama yang opsinya sudah diubah/dihapus,
                // supaya jumlahnya tetap sama dengan banyaknya jawaban.
                $counts = $values->countBy(fn ($value) => (string) $value);
                $current = array_map('strval', $question->options ?? []);
                $row['options'] = [];
                foreach ($current as $option) {
                    $row['options'][] = ['label' => $option, 'count' => $counts[$option] ?? 0, 'removed' => false];
                }
                foreach ($counts as $value => $count) {
                    if (! in_array((string) $value, $current, true)) {
                        $row['options'][] = ['label' => (string) $value, 'count' => $count, 'removed' => true];
                    }
                }
            } else {
                $row['entries'] = $answered->take($textLimit)->map(fn (TrainingEvaluation $evaluation) => [
                    'name' => $evaluation->user->nama_lengkap ?? 'Peserta',
                    'at' => $evaluation->submitted_at,
                    'text' => $evaluation->answerTo($question),
                ])->all();
            }

            $rows[] = $row;
        }

        $averages = $evaluations->map(fn (TrainingEvaluation $evaluation) => $evaluation->average($questions))->filter();

        return [
            'count' => $evaluations->count(),
            'average' => $averages->isEmpty() ? null : round($averages->avg(), 2),
            'questions' => $rows,
        ];
    }
}
