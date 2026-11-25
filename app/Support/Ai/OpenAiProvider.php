<?php

namespace App\Support\Ai;

use Exception;
use Illuminate\Support\Facades\Http;

class OpenAiProvider extends AiProvider
{
    public const API_BASE = 'https://api.openai.com/v1';

    public function key(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function keyUrl(): string
    {
        return 'https://platform.openai.com/api-keys';
    }

    public function listModels(string $apiKey): array
    {
        $response = Http::timeout(20)->withToken($apiKey)->get(self::API_BASE.'/models');
        if (! $response->successful()) {
            throw $response->status() === 401
                ? $this->invalidKey()
                : new AiException('Gagal memuat model OpenAI: '.$this->errorMessage($response));
        }

        // Hanya model chat; model audio, gambar, embedding dan sejenisnya tidak bisa membuat soal.
        return collect($response->json('data', []))
            ->filter(fn (array $model) => preg_match('/^(gpt-|o\d|chatgpt-)/', $model['id'])
                && ! preg_match('/(audio|realtime|transcribe|tts|image|embedding|instruct|search|moderation)/', $model['id']))
            ->sortByDesc('created')
            ->mapWithKeys(fn (array $model) => [$model['id'] => $model['id']])
            ->all();
    }

    public function generate(array $parts, string $apiKey, string $model, array $options = []): string
    {
        $content = $this->translateParts(
            $parts,
            fn (string $text) => ['type' => 'text', 'text' => $text],
            fn (string $mime, string $data) => ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$data}"]],
            fn (string $data, int $number) => ['type' => 'file', 'file' => [
                'filename' => "materi-{$number}.pdf",
                'file_data' => "data:application/pdf;base64,{$data}",
            ]],
        );

        // Tanpa temperature: model reasoning OpenAI hanya menerima nilai bawaan.
        $response = Http::timeout($options['timeout'] ?? 300)
            ->withToken($apiKey)
            ->post(self::API_BASE.'/chat/completions', [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $content]],
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            throw $response->status() === 401 ? $this->invalidKey() : new Exception("OpenAI ({$model}): ".$this->errorMessage($response));
        }

        if ($refusal = $response->json('choices.0.message.refusal')) {
            throw new AiException('OpenAI menolak permintaan ini: '.$refusal);
        }

        return (string) $response->json('choices.0.message.content', '');
    }
}
