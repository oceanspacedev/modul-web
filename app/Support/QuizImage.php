<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gambar soal kuis (pertanyaan dan pilihan jawaban) untuk kuis pelatihan dan kuis modul.
 *
 * Form manual mengirim file langsung (question_image, option_images[a], ...) beserta centang hapus
 * (remove_question_image, remove_option_images[a]). Preview soal AI mengunggah tiap gambar lebih dulu
 * lewat endpoint upload-image, lalu mengirim path hasilnya saat menyimpan.
 */
class QuizImage
{
    public const DIRECTORY = 'quiz-images';

    private const PATH_PATTERN = '/^quiz-images\/[A-Za-z0-9]+\.(jpe?g|png|webp)$/';

    /**
     * Aturan validasi untuk file gambar yang diunggah.
     *
     * @return array<int, mixed>
     */
    public static function fileRules(): array
    {
        return ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
    }

    /**
     * Aturan validasi untuk path gambar yang sudah diunggah lewat upload-image.
     *
     * @return array<int, mixed>
     */
    public static function pathRules(): array
    {
        // bail: path yang tidak cocok pola (mis. "../.env") tidak boleh sampai dicek ke storage.
        return [
            'bail',
            'nullable',
            'string',
            'regex:'.self::PATH_PATTERN,
            function (string $attribute, mixed $value, \Closure $fail) {
                if (! Storage::exists($value)) {
                    $fail('Gambar tidak ditemukan. Silakan unggah ulang gambarnya.');
                }
            },
        ];
    }

    public static function store(UploadedFile $file, string $field = 'image'): string
    {
        $path = $file->storeAs(self::DIRECTORY, Str::random(30).'.'.$file->extension());

        if ($path === false) {
            throw ValidationException::withMessages([
                $field => 'Gambar gagal disimpan ke storage server. Silakan coba lagi.',
            ]);
        }

        return $path;
    }

    public static function delete(?string ...$paths): void
    {
        foreach (array_filter($paths) as $path) {
            if (Storage::exists($path)) {
                Storage::delete($path);
            }
        }
    }

    public static function url(?string $path): ?string
    {
        return $path ? StorageUrl::for($path) : null;
    }

    /**
     * Apakah soal akan punya gambar pertanyaan setelah form manual disimpan (untuk validasi "teks atau gambar").
     */
    public static function questionHasImageAfterSave(Request $request, ?Model $existing): bool
    {
        return $request->hasFile('question_image')
            || (filled($existing?->question_image) && ! $request->boolean('remove_question_image'));
    }

    /**
     * Huruf pilihan yang akan punya gambar setelah form manual disimpan (untuk validasi "teks atau gambar").
     *
     * @param  array<int, string>  $letters
     * @return array<string, bool>
     */
    public static function optionsWithImageAfterSave(Request $request, ?Model $existing, array $letters): array
    {
        $result = [];
        foreach ($letters as $letter) {
            $result[$letter] = $request->hasFile('option_images.'.$letter)
                || (filled($existing?->option_images[$letter] ?? null) && ! $request->boolean('remove_option_images.'.$letter));
        }

        return $result;
    }

    /**
     * Simpan gambar dari form manual dan kembalikan nilai kolom question_image & option_images.
     * Gambar lama yang diganti atau dihapus ikut dihapus dari storage.
     *
     * @param  array<int, string>  $letters  huruf pilihan yang dipakai; selain itu gambarnya dibuang (mis. soal essay)
     * @return array{question_image: ?string, option_images: ?array<string, string>}
     */
    public static function fromForm(Request $request, ?Model $existing, array $letters): array
    {
        $questionImage = self::resolveSlot(
            $request->file('question_image'),
            $request->boolean('remove_question_image'),
            $existing?->question_image,
        );

        $oldOptions = $existing?->option_images ?? [];
        $optionImages = [];
        foreach ($letters as $letter) {
            $path = self::resolveSlot(
                $request->file('option_images.'.$letter),
                $request->boolean('remove_option_images.'.$letter),
                $oldOptions[$letter] ?? null,
            );
            if ($path) {
                $optionImages[$letter] = $path;
            }
        }

        // Gambar milik pilihan yang tidak dipakai lagi (mis. soal diubah jadi essay).
        self::delete(...array_values(array_diff_key($oldOptions, array_flip($letters))));

        return ['question_image' => $questionImage, 'option_images' => $optionImages ?: null];
    }

    /**
     * Nilai kolom gambar dari data soal AI yang sudah divalidasi (path dari upload-image).
     *
     * @param  array<string, mixed>  $question
     * @param  array<int, string>  $letters
     * @return array{question_image: ?string, option_images: ?array<string, string>}
     */
    public static function fromPayload(array $question, array $letters): array
    {
        $optionImages = array_filter(array_intersect_key($question['option_images'] ?? [], array_flip($letters)));

        return [
            'question_image' => $question['question_image'] ?? null,
            'option_images' => $optionImages ?: null,
        ];
    }

    private static function resolveSlot(?UploadedFile $file, bool $remove, ?string $current): ?string
    {
        if ($file) {
            $path = self::store($file);
            self::delete($current);

            return $path;
        }

        if ($remove) {
            self::delete($current);

            return null;
        }

        return $current;
    }
}
