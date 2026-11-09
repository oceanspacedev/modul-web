<?php

namespace App\Models\Concerns;

use App\Support\QuizImage;

/**
 * Gambar soal (question_image) dan gambar per pilihan jawaban (option_images: huruf => path).
 * File gambar ikut dihapus saat soalnya dihapus.
 */
trait HasQuizImages
{
    public static function bootHasQuizImages(): void
    {
        static::deleting(function (self $question) {
            QuizImage::delete($question->question_image, ...array_values($question->option_images ?? []));
        });
    }

    public function initializeHasQuizImages(): void
    {
        $this->mergeCasts(['option_images' => 'array']);
    }

    public function imageUrl(): ?string
    {
        return QuizImage::url($this->question_image);
    }

    public function optionImage(string $letter): ?string
    {
        return $this->option_images[$letter] ?? null;
    }

    public function optionImageUrl(string $letter): ?string
    {
        return QuizImage::url($this->optionImage($letter));
    }

    /**
     * URL gambar per huruf pilihan, untuk dikirim ke JavaScript.
     *
     * @return array<string, string>
     */
    public function optionImageUrls(): array
    {
        return array_map(fn (string $path) => QuizImage::url($path), array_filter($this->option_images ?? []));
    }

    /**
     * Soal punya gambar di pertanyaan atau di salah satu pilihannya.
     */
    public function hasImages(): bool
    {
        return filled($this->question_image) || array_filter($this->option_images ?? []) !== [];
    }

    /**
     * Pilihan dianggap terisi bila punya teks atau gambar.
     */
    public function hasOption(string $letter): bool
    {
        return filled($this->{'option_'.$letter}) || filled($this->optionImage($letter));
    }

    /**
     * Pilihan yang terisi, mis. ['a' => 'Cuci tangan', 'b' => ''] (b hanya bergambar).
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];
        foreach (self::OPTION_LETTERS as $letter) {
            if ($this->hasOption($letter)) {
                $options[$letter] = (string) $this->{'option_'.$letter};
            }
        }

        return $options;
    }
}
