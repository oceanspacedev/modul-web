<?php

namespace App\Support\Ai;

use Exception;
use Illuminate\Support\Facades\Http;

class GeminiProvider extends AiProvider
{
    public const API_BASE = 'https://generativelanguage.googleapis.com';

    public function key(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return 'Google Gemini';
    }

    public function keyUrl(): string
    {
        return 'https://aistudio.google.com/app/apikey';
    }

    public function supportsVideo(): bool
    {
        return true;
    }

    public function defaultModel(): ?string
    {
        return config('services.gemini.model') ?: 'gemini-3.5-flash-lite';
    }

    public function envApiKey(): ?string
    {
        return config('services.gemini.api_key') ?: null;
    }

    public function fallbackModels(): array
    {
        return ['gemini-3.5-flash-lite', 'gemini-3.5-flash', 'gemini-3-flash-preview', 'gemini-flash-latest', 'gemini-3.8-flash'];
    }

    public function listModels(string $apiKey): array
    {
        $response = Http::timeout(20)->get(self::API_BASE.'/v1beta/models', ['key' => $apiKey, 'pageSize' => 1000]);
        if (! $response->successful()) {
            throw $this->isInvalidKey($this->errorMessage($response))
                ? $this->invalidKey()
                : new AiException('Gagal memuat model Gemini: '.$this->errorMessage($response));
        }

        // Hanya model Gemini penghasil teks; generateContent juga dipakai model musik (Lyria), gambar, suara dan lainnya.
        return collect($response->json('models', []))
            ->filter(fn (array $model) => in_array('generateContent', $model['supportedGenerationMethods'] ?? [], true))
            ->mapWithKeys(fn (array $model) => [str_replace('models/', '', $model['name']) => $model['displayName'] ?? $model['name']])
            ->filter(fn (string $label, string $id) => str_starts_with($id, 'gemini-')
                && ! preg_match('/(tts|image|audio|live|embedding|robotics|computer-use)/', $id))
            ->sortKeys()
            ->all();
    }

    public function generate(array $parts, string $apiKey, string $model, array $options = []): string
    {
        return $this->send($apiKey, $model, $options['timeout'] ?? 300, [
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature' => $options['temperature'] ?? 0.3,
                // Resolusi rendah memangkas token video kira-kira 3x; isi materi tetap terbaca dari audio dan teks layar.
                ...(! empty($options['has_video']) ? ['media_resolution' => 'MEDIA_RESOLUTION_LOW'] : []),
            ],
        ]);
    }

    public function chat(array $messages, string $apiKey, string $model): string
    {
        return $this->send($apiKey, $model, self::CHAT_TIMEOUT, [
            'system_instruction' => ['parts' => [['text' => self::CHAT_SYSTEM]]],
            'contents' => array_map(fn (array $message) => [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['text']]],
            ], $messages),
        ]);
    }

    /** @throws AiException|Exception */
    private function send(string $apiKey, string $model, int $timeout, array $payload): string
    {
        $response = Http::timeout($timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(self::API_BASE."/v1beta/models/{$model}:generateContent?key=".urlencode($apiKey), $payload);

        if (! $response->successful()) {
            $message = $this->errorMessage($response);

            throw $this->isInvalidKey($message) ? $this->invalidKey() : new Exception("Gemini ({$model}): {$message}");
        }

        return collect($response->json('candidates.0.content.parts', []))
            ->reject(fn (array $part) => ! empty($part['thought']))
            ->pluck('text')
            ->filter()
            ->implode('');
    }

    private function isInvalidKey(string $message): bool
    {
        return str_contains($message, 'API_KEY_INVALID') || str_contains($message, 'API key not valid');
    }
}
