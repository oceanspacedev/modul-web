<?php

namespace App\Support;

/**
 * Kosakata aturan kuis yang sama untuk Video Materi, Dokumen, Modul Belajar, dan Pelatihan: berapa kali boleh
 * dikerjakan (null = tanpa batas) dan apa yang dilihat peserta setelah selesai.
 */
final class QuizRules
{
    /** Pilihan batas percobaan yang ditawarkan di form; nilai lain yang sudah tersimpan tetap ikut tampil. */
    public const ATTEMPT_CHOICES = [1, 2, 3, 5];

    public const MAX_ATTEMPTS = 255;

    /**
     * Opsi <select> batas percobaan: ['1' => '1 kali', ..., '' => 'Tanpa batas'].
     *
     * @return array<string, string>
     */
    public static function attemptOptions(?int $current = null): array
    {
        $choices = self::ATTEMPT_CHOICES;
        if ($current !== null && ! in_array($current, $choices, true)) {
            $choices[] = $current;
            sort($choices);
        }

        $options = [];
        foreach ($choices as $count) {
            $options[(string) $count] = self::attemptsLabel($count);
        }
        $options[''] = self::attemptsLabel(null);

        return $options;
    }

    public static function attemptsLabel(?int $max): string
    {
        return $max === null ? 'Tanpa batas' : $max.' kali';
    }

    /**
     * Opsi <select> hasil yang dilihat peserta: ['0' => 'Nilai saja', '1' => 'Nilai dan jawaban'].
     *
     * @return array<string, string>
     */
    public static function answerOptions(): array
    {
        return ['0' => self::answersLabel(false), '1' => self::answersLabel(true)];
    }

    public static function answersLabel(bool $showsAnswers): string
    {
        return $showsAnswers ? 'Nilai dan jawaban' : 'Nilai saja';
    }

    /**
     * Satu baris aturan untuk peserta dan pengelola, mis. "Boleh dikerjakan 2 kali · hasil: nilai saja".
     */
    public static function summary(?int $max, bool $showsAnswers): string
    {
        $attempts = $max === null ? 'Boleh diulang tanpa batas' : 'Boleh dikerjakan '.$max.' kali';

        return $attempts.' · hasil: '.mb_strtolower(self::answersLabel($showsAnswers));
    }

    /**
     * Sisa percobaan untuk peserta, mis. "sisa 1 kali"; null = tanpa batas.
     */
    public static function remainingLabel(?int $remaining): string
    {
        return match (true) {
            $remaining === null => 'boleh diulang tanpa batas',
            $remaining === 0 => 'batas percobaan habis',
            default => 'sisa '.$remaining.' kali',
        };
    }

    /**
     * Aturan validasi untuk field batas percobaan (kosong = tanpa batas).
     *
     * @return list<string>
     */
    public static function attemptRules(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ATTEMPTS];
    }
}
