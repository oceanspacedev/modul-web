<?php

namespace App\Support\Ai;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Pemanggil AI bersama untuk semua fitur (soal, revisi soal, scan kamera, Dokumen Builder): memakai provider, API key
 * dan model dari Pengaturan AI, lalu mencoba model cadangan provider bila model terpilih gagal.
 */
class AiClient
{
    public function __construct(
        private AiProvider $provider,
        private ?string $apiKey,
        private ?string $model,
    ) {}

    /** Klien untuk provider aktif. $apiKey menggantikan key tersimpan (isian "Ubah key" di form soal training). */
    public static function current(?string $apiKey = null): self
    {
        $provider = AiSettings::provider();

        return new self($provider, $apiKey ?: AiSettings::apiKey($provider), AiSettings::model($provider));
    }

    public function provider(): AiProvider
    {
        return $this->provider;
    }

    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey) && filled($this->model);
    }

    /**
     * Kirim part (format Gemini) dan kembalikan teks jawaban AI.
     *
     * @param  array<int, array<string, mixed>>  $parts
     * @param  array{temperature?: float, timeout?: int, has_video?: bool}  $options
     *
     * @throws Exception
     */
    public function generate(array $parts, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new AiException(AiSettings::notConfiguredMessage());
        }

        $lastError = null;
        $models = array_values(array_unique(array_filter([$this->model, ...$this->provider->fallbackModels()])));

        foreach ($models as $model) {
            try {
                $text = $this->provider->generate($parts, $this->apiKey, $model, $options);
            } catch (AiException $e) {
                throw $e;
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                Log::warning("Model AI {$this->provider->key()}/{$model} gagal: {$lastError}");

                continue;
            }

            if (trim($text) === '') {
                throw new AiException("{$this->provider->label()} tidak mengembalikan respons teks yang valid.");
            }

            return $text;
        }

        throw new Exception($lastError ?: "Gagal menghubungi {$this->provider->label()}. Silakan coba beberapa saat lagi.");
    }
}
