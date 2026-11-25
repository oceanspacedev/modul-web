<?php

namespace App\Support\Ai;

use Illuminate\Http\Client\Response;

/**
 * Satu penyedia AI (Gemini, OpenAI, Anthropic). Materi dikirim dalam format part Gemini yang sudah dipakai service
 * AI (['text' => ...], ['inline_data' => [mime_type, data]], ['file_data' => [file_uri]]); provider lain
 * menerjemahkannya ke format API-nya sendiri. Menambah provider berarti menambah satu kelas turunan dan
 * mendaftarkannya di AiProviders.
 */
abstract class AiProvider
{
    /** Nilai yang disimpan di ai_settings.provider. */
    abstract public function key(): string;

    abstract public function label(): string;

    /** Halaman untuk membuat API key. */
    abstract public function keyUrl(): string;

    /**
     * Model yang dapat dipakai untuk membuat teks, [id => label], dibaca langsung dari API provider.
     *
     * @return array<string, string>
     *
     * @throws AiException
     */
    abstract public function listModels(string $apiKey): array;

    /**
     * Kirim materi + prompt dan kembalikan teks jawabannya (diharapkan JSON).
     *
     * @param  array<int, array<string, mixed>>  $parts
     * @param  array{temperature?: float, timeout?: int, has_video?: bool}  $options
     *
     * @throws AiException kesalahan yang tidak perlu dicoba dengan model lain
     * @throws \Exception kesalahan lain (model tidak tersedia, timeout, ...)
     */
    abstract public function generate(array $parts, string $apiKey, string $model, array $options = []): string;

    /** Hanya Gemini yang bisa membaca video (file upload dan link YouTube). */
    public function supportsVideo(): bool
    {
        return false;
    }

    /** Model bawaan bila admin belum memilih model. */
    public function defaultModel(): ?string
    {
        return null;
    }

    /** API key cadangan dari .env bila Pengaturan AI belum diisi. */
    public function envApiKey(): ?string
    {
        return null;
    }

    /**
     * Model yang dicoba setelah model terpilih gagal (bukan karena API key).
     *
     * @return array<int, string>
     */
    public function fallbackModels(): array
    {
        return [];
    }

    protected function errorMessage(Response $response): string
    {
        return (string) ($response->json('error.message') ?? ('HTTP '.$response->status()));
    }

    protected function invalidKey(): AiException
    {
        return new AiException("API key {$this->label()} tidak valid. Periksa kembali di Pengaturan AI.");
    }

    /**
     * Terjemahkan part Gemini lewat tiga pemeta: teks, gambar, PDF. Video dan file lain ditolak dengan pesan yang
     * jelas karena provider ini tidak bisa membacanya.
     *
     * @param  array<int, array<string, mixed>>  $parts
     * @param  callable(string): array  $text
     * @param  callable(string, string): array  $image  menerima mime dan base64
     * @param  callable(string, int): array  $pdf  menerima base64 dan nomor urut PDF
     * @return array<int, array<string, mixed>>
     *
     * @throws AiException
     */
    protected function translateParts(array $parts, callable $text, callable $image, callable $pdf): array
    {
        $blocks = [];
        $pdfCount = 0;

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $blocks[] = $text((string) $part['text']);
            } elseif (isset($part['inline_data'])) {
                $mime = (string) ($part['inline_data']['mime_type'] ?? '');
                $data = (string) ($part['inline_data']['data'] ?? '');

                $blocks[] = match (true) {
                    str_starts_with($mime, 'image/') => $image($mime, $data),
                    $mime === 'application/pdf' => $pdf($data, ++$pdfCount),
                    default => throw new AiException("{$this->label()} tidak bisa membaca file {$mime}. Gunakan PDF, DOCX, TXT atau gambar, atau ganti ke Gemini di Pengaturan AI."),
                };
            } elseif (isset($part['file_data'])) {
                throw new AiException("{$this->label()} tidak bisa membaca video. Ganti ke Gemini di Pengaturan AI.");
            }
        }

        return $blocks;
    }
}
