<?php

namespace App\Support\Ai;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AnthropicProvider extends AiProvider
{
    public const API_BASE = 'https://api.anthropic.com/v1';

    private const VERSION = '2023-06-01';

    public function key(): string
    {
        return 'anthropic';
    }

    public function label(): string
    {
        return 'Anthropic Claude';
    }

    public function keyUrl(): string
    {
        return 'https://console.anthropic.com/settings/keys';
    }

    public function listModels(string $apiKey): array
    {
        $response = $this->client($apiKey, 20)->get(self::API_BASE.'/models', ['limit' => 1000]);
        if (! $response->successful()) {
            throw $response->status() === 401
                ? $this->invalidKey()
                : new AiException('Gagal memuat model Claude: '.$this->errorMessage($response));
        }

        return collect($response->json('data', []))
            ->mapWithKeys(fn (array $model) => [$model['id'] => $model['display_name'] ?? $model['id']])
            ->all();
    }

    public function generate(array $parts, string $apiKey, string $model, array $options = []): string
    {
        $content = $this->translateParts(
            $parts,
            fn (string $text) => ['type' => 'text', 'text' => $text],
            fn (string $mime, string $data) => ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $data]],
            fn (string $data) => ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $data]],
        );

        // Tanpa temperature: model Claude terbaru menolak parameter sampling.
        return $this->send($apiKey, $options['timeout'] ?? 300, [
            'model' => $model,
            'max_tokens' => 16000,
            'system' => 'Balas hanya dengan JSON murni sesuai format yang diminta, tanpa teks lain.',
            'messages' => [['role' => 'user', 'content' => $content]],
        ]);
    }

    public function chat(array $messages, string $apiKey, string $model): string
    {
        return $this->send($apiKey, self::CHAT_TIMEOUT, [
            'model' => $model,
            'max_tokens' => 2048,
            'system' => self::CHAT_SYSTEM,
            'messages' => array_map(fn (array $message) => ['role' => $message['role'], 'content' => $message['text']], $messages),
        ]);
    }

    /** @throws AiException|Exception */
    private function send(string $apiKey, int $timeout, array $payload): string
    {
        $model = $payload['model'];
        $response = $this->client($apiKey, $timeout)->post(self::API_BASE.'/messages', $payload);

        if (! $response->successful()) {
            throw $response->status() === 401 ? $this->invalidKey() : new Exception("Claude ({$model}): ".$this->errorMessage($response));
        }

        if ($response->json('stop_reason') === 'refusal') {
            throw new AiException('Claude menolak permintaan ini. Ubah materi atau instruksinya lalu coba lagi.');
        }

        return collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');
    }

    private function client(string $apiKey, int $timeout): PendingRequest
    {
        return Http::timeout($timeout)->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => self::VERSION,
        ]);
    }
}
