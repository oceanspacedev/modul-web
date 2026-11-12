<?php

namespace App\Exports;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Support\StorageUrl;

/**
 * Kolom rekap per langkah peserta (presensi, pretest, form evaluasi, posttest), dipakai rekap satu pelatihan
 * (TrainingRekapExport, rinci) dan rekap nilai gabungan (TrainingScoresSheet, ringkas). Hanya langkah yang dipakai
 * pelatihan yang mendapat kolom, dalam Urutan Langkah Peserta. Pretest dan posttest memakai kolom yang sama, dan
 * kolomnya mengikuti soal yang dibuat: fase tanpa soal essay cukup satu kolom nilai, tanpa kolom PG/essay terpisah.
 * Langkah sesi tidak punya kolom.
 */
class TrainingStepColumns
{
    private const PHASES = [
        Training::STEP_PRETEST => [Training::PHASE_PRETEST, 'Pretest'],
        Training::STEP_POSTTEST => [Training::PHASE_POSTTEST, 'Posttest'],
    ];

    /**
     * Langkah pelatihan ini yang mendapat kolom, dalam urutan pilihan pemateri.
     *
     * @return list<string>
     */
    public static function stepsOf(Training $training): array
    {
        return array_values(array_filter($training->portalStepOrder(), fn (string $step) => self::uses($training, $step)));
    }

    public static function uses(Training $training, string $step): bool
    {
        return match ($step) {
            Training::STEP_ATTENDANCE => (bool) $training->has_attendance,
            Training::STEP_PRETEST => (bool) $training->has_pretest,
            Training::STEP_EVALUATION => $training->hasEvaluationForm(),
            Training::STEP_POSTTEST => (bool) $training->has_posttest,
            default => false,
        };
    }

    /**
     * Fase langkah ini (pretest/posttest) memakai soal essay, atau sudah ada hasil yang essay-nya dinilai/menunggu
     * (soalnya bisa saja dihapus belakangan). Butuh relasi questions dan hasil kuis peserta.
     */
    public static function hasEssay(Training $training, string $step): bool
    {
        if (! isset(self::PHASES[$step]) || ! self::uses($training, $step)) {
            return false;
        }

        $phase = self::PHASES[$step][0];

        return $training->questionsFor($phase)->contains(fn ($question) => $question->type === 'essay')
            || $training->participants->contains(fn (TrainingParticipant $participant) => in_array($participant->resultFor($phase)?->essay_status, ['graded', 'pending'], true));
    }

    /**
     * @param  bool  $detailed  rekap satu pelatihan: ditambah waktu/bukti presensi dan catatan, pelanggaran, serta
     *                          status submit kuis
     * @param  bool  $essay  fase ini punya soal essay: nilai dipecah menjadi PG, essay, dan akhir
     * @return list<string>
     */
    public static function headings(string $step, bool $detailed = false, bool $essay = false): array
    {
        if (isset(self::PHASES[$step])) {
            $label = self::PHASES[$step][1];

            return [
                "Status {$label}",
                ...($essay
                    ? ["Nilai PG {$label} (0-100)", "Status Essay {$label}", "Nilai Essay {$label} (0-100)", "Nilai Akhir {$label} (0-100)"]
                    : ["Nilai {$label} (0-100)"]),
                ...($essay && $detailed ? ["Catatan Essay {$label}"] : []),
                ...($detailed ? ["Pelanggaran Tab {$label}", "Status Submit {$label}"] : []),
                "Waktu Submit {$label}",
            ];
        }

        return match ($step) {
            Training::STEP_ATTENDANCE => $detailed ? ['Status Kehadiran', 'Waktu Absen', 'Bukti Screenshot'] : ['Kehadiran'],
            Training::STEP_EVALUATION => ['Status Evaluasi', 'Rata-rata Evaluasi (1-5)', 'Waktu Isi Evaluasi'],
            default => [],
        };
    }

    /**
     * Isi kolom sebuah langkah untuk satu peserta; "-" di semua kolomnya bila pelatihannya tidak memakai langkah itu
     * (rekap gabungan). Nilai yang belum ada ditulis "-", bukan 0: nilai 0 berarti mengerjakan dan salah semua.
     * Butuh relasi quizResult, pretestResult, dan (bila ada form evaluasi) evaluation pada peserta.
     *
     * @return list<string|float>
     */
    public static function cells(string $step, Training $training, TrainingParticipant $participant, bool $detailed = false, bool $essay = false): array
    {
        if (! self::uses($training, $step)) {
            return array_fill(0, count(self::headings($step, $detailed, $essay)), '-');
        }

        if (isset(self::PHASES[$step])) {
            return self::phaseCells($participant->resultFor(self::PHASES[$step][0]), $detailed, $essay);
        }

        if ($step === Training::STEP_ATTENDANCE) {
            $offCam = $participant->is_off_cam ? ' (Off Cam '.($participant->off_cam_count ?: 1).'x)' : '';
            $status = match ($participant->attendance_status) {
                'hadir' => 'Hadir',
                'tidak_hadir' => 'Tidak Hadir',
                default => 'Belum Absen',
            };

            return $detailed ? [
                $status.$offCam,
                $participant->attended_at?->format('d-m-Y H:i') ?? '-',
                // File dibuka lama setelah export, jadi tautannya ditandatangani selama yang diizinkan S3.
                $participant->attendance_proof ? StorageUrl::for($participant->attendance_proof, StorageUrl::MAX_TTL_MINUTES) : '-',
            ] : [$status];
        }

        $evaluation = $participant->evaluation;
        $average = $evaluation?->average($training->evaluationQuestions);

        return [
            $evaluation ? 'Sudah Mengisi' : 'Belum Mengisi',
            $average !== null ? (float) $average : '-',
            $evaluation?->submitted_at?->format('d-m-Y H:i') ?? '-',
        ];
    }

    /**
     * @return list<string|float>
     */
    private static function phaseCells(?TrainingQuizResult $result, bool $detailed, bool $essay): array
    {
        return [
            $result ? 'Sudah Mengerjakan' : 'Belum Mengerjakan',
            ...($essay ? [
                $result ? (float) ($result->mc_score ?? $result->score) : '-',
                match ($result?->essay_status) {
                    'graded' => 'Sudah Dinilai',
                    'pending' => 'Menunggu Dinilai',
                    default => 'Tidak Ada Essay',
                },
                $result && $result->essay_score !== null ? (float) $result->essay_score : '-',
            ] : []),
            $result ? (float) $result->score : '-',
            ...($essay && $detailed ? [$result ? ($result->essay_feedback ?? '-') : '-'] : []),
            ...($detailed ? [
                $result ? (($result->tab_switch_count ?? 0).' kali') : '-',
                $result ? ($result->is_force_submitted ? 'Auto-Submit (Melanggar)' : 'Normal') : '-',
            ] : []),
            $result?->submitted_at?->format('d-m-Y H:i') ?? '-',
        ];
    }
}
