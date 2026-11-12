<?php

namespace App\Support;

use App\Models\TrainingEvaluation;
use Illuminate\Support\Collection;

/**
 * Pertanyaan form evaluasi pelatihan: skala 1-5 tentang pemateri dan pelatihan, ditambah satu masukan bebas.
 * Identitas peserta (nama, jabatan, divisi) serta tanggal dan materi tidak ditanyakan karena sudah ada di sistem.
 */
final class TrainingEvaluationForm
{
    public const SCALE_MIN = 1;

    public const SCALE_MAX = 5;

    public const COMMENT_MAX = 1000;

    private const AGREE = ['Sangat Tidak Setuju', 'Sangat Setuju'];

    /**
     * Bagian form berurutan: judul bagian => [kunci => [pertanyaan, label nilai terendah, label nilai tertinggi]].
     * Kunci dipakai sebagai nama field dan disimpan di training_evaluations.ratings, jadi jangan diganti.
     */
    private const SECTIONS = [
        'Pemateri' => [
            'trainer_clarity' => ['Instruksi yang diberikan pemateri jelas dan mudah dipahami', ...self::AGREE],
            'trainer_mastery' => ['Pemateri memiliki pemahaman yang baik tentang materi yang disampaikan', ...self::AGREE],
            'trainer_engagement' => ['Pemateri dapat mendorong peserta untuk aktif berpartisipasi selama sesi', ...self::AGREE],
            'trainer_overall' => ['Secara keseluruhan, seberapa puas Anda dengan pemateri hari ini', 'Sangat Tidak Puas', 'Sangat Puas'],
        ],
        'Materi & Pelatihan' => [
            'material_useful' => ['Materi yang diberikan sangat membantu saya di lapangan/pekerjaan sehari-hari', ...self::AGREE],
            'meets_expectation' => ['Pelatihan ini memenuhi harapan saya', ...self::AGREE],
            'well_organized' => ['Sesi pelatihan terorganisir dengan baik', ...self::AGREE],
            'duration_fit' => ['Durasi atau waktu pelatihan sudah sesuai dan cukup', ...self::AGREE],
            'overall' => ['Bagaimana Anda menilai pelatihan ini secara keseluruhan', 'Sangat Buruk', 'Sangat Baik'],
        ],
    ];

    /**
     * @return array<string, array<string, array{text: string, low: string, high: string}>>
     */
    public static function sections(): array
    {
        return array_map(
            fn (array $questions) => array_map(fn (array $q) => ['text' => $q[0], 'low' => $q[1], 'high' => $q[2]], $questions),
            self::SECTIONS,
        );
    }

    /**
     * Semua pertanyaan skala tanpa bagiannya: kunci => teks pertanyaan.
     *
     * @return array<string, string>
     */
    public static function questions(): array
    {
        $questions = [];
        foreach (self::SECTIONS as $section) {
            foreach ($section as $key => $question) {
                $questions[$key] = $question[0];
            }
        }

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
     * Aturan validasi kiriman peserta: setiap skala wajib, masukan opsional.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $rules = ['ratings' => ['required', 'array'], 'comment' => ['nullable', 'string', 'max:'.self::COMMENT_MAX]];
        foreach (array_keys(self::questions()) as $key) {
            $rules['ratings.'.$key] = ['required', 'integer', 'between:'.self::SCALE_MIN.','.self::SCALE_MAX];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'ratings.required' => 'Pilih nilai untuk setiap pertanyaan.',
            'ratings.*.required' => 'Masih ada pertanyaan yang belum dinilai.',
            'ratings.*.between' => 'Nilai harus antara '.self::SCALE_MIN.' dan '.self::SCALE_MAX.'.',
            'comment.max' => 'Masukan maksimal '.self::COMMENT_MAX.' karakter.',
        ];
    }

    /**
     * Rata-rata per pertanyaan dan keseluruhan untuk halaman detail pelatihan.
     *
     * @param  Collection<int, TrainingEvaluation>  $evaluations
     * @return array{count: int, average: float|null, questions: array<string, array{text: string, average: float|null}>}
     */
    public static function summarize(Collection $evaluations): array
    {
        $questions = [];
        foreach (self::questions() as $key => $text) {
            $values = $evaluations->map(fn (TrainingEvaluation $evaluation) => $evaluation->ratings[$key] ?? null)->filter();
            $questions[$key] = ['text' => $text, 'average' => $values->isEmpty() ? null : round($values->avg(), 2)];
        }

        $averages = $evaluations->map(fn (TrainingEvaluation $evaluation) => $evaluation->average())->filter();

        return [
            'count' => $evaluations->count(),
            'average' => $averages->isEmpty() ? null : round($averages->avg(), 2),
            'questions' => $questions,
        ];
    }
}
