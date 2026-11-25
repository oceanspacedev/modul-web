<?php

namespace App\Services;

use App\Models\DocumentDraft;
use App\Support\Ai\AiSettings;
use App\Support\DocumentBuilderContent;
use Exception;

/**
 * Menyusun isi Dokumen Builder dengan AI dari jawaban form bertahap. Memakai pemanggil AI yang sama dengan
 * pembuat soal (model cadangan, pesan error API key), hanya prompt dan bentuk hasilnya yang berbeda.
 */
class DocumentBuilderAiService extends GeminiQuestionService
{
    /**
     * @return array<string, mixed> isi dokumen per bagian template
     *
     * @throws Exception
     */
    public function compose(DocumentDraft $draft): array
    {
        if (! $this->isConfigured()) {
            throw new Exception(AiSettings::notConfiguredMessage());
        }

        $template = $draft->template();
        $response = $this->callAi([['text' => $this->prompt($draft, $template)]]);
        $decoded = $this->decodeJsonText($response);

        if (! is_array($decoded)) {
            throw new Exception('AI tidak mengembalikan dokumen yang bisa dibaca. Silakan coba lagi.');
        }

        // Model kadang membungkus hasilnya: {"sections": {...}}.
        $raw = is_array($decoded['sections'] ?? null) ? $decoded['sections'] : $decoded;
        $content = DocumentBuilderContent::normalize($template['sections'], $raw);

        if (DocumentBuilderContent::isEmpty($content)) {
            throw new Exception('AI mengembalikan dokumen kosong. Lengkapi jawaban form lalu coba lagi.');
        }

        return $content;
    }

    protected function prompt(DocumentDraft $draft, array $template): string
    {
        $category = $draft->category;

        $answers = collect($template['questions'])
            ->map(fn (array $q) => [$q['label'], trim((string) ($draft->answers[$q['key']] ?? ''))])
            ->filter(fn (array $pair) => $pair[1] !== '')
            ->map(fn (array $pair) => "- {$pair[0]}: {$pair[1]}")
            ->implode("\n");

        $shapes = [
            'text' => 'string berisi paragraf (boleh beberapa paragraf dipisah \\n)',
            'list' => 'array of string, satu poin per elemen',
            'items' => 'array of object {"title": string, "text": string}',
            'steps' => 'array of object {"title": string, "text": string}, urut sesuai pelaksanaan',
        ];

        $sections = collect($template['sections'])
            ->map(function (array $s) use ($shapes) {
                $labels = isset($s['item_labels']) ? " (title = {$s['item_labels'][0]}, text = {$s['item_labels'][1]})" : '';

                return "- \"{$s['key']}\" ({$s['label']}): {$s['hint']}. Format: {$shapes[$s['type']]}{$labels}.";
            })
            ->implode("\n");

        $context = collect([
            'Perusahaan / badan usaha' => $draft->company,
            'Departemen' => $draft->department,
        ])->filter()->map(fn ($v, $k) => "- {$k}: {$v}")->implode("\n");

        $fromRequest = collect($draft->requestContext())->map(fn ($v, $k) => "- {$k}: {$v}")->implode("\n");
        $fromRequest = $fromRequest !== ''
            ? "\nInfo dari Permintaan Dokumen (pengajuan karyawan; pakai sebagai latar belakang, jawaban penyusun di atas lebih diutamakan):\n{$fromRequest}\n"
            : '';

        return <<<PROMPT
Anda adalah konsultan Organization Development yang berpengalaman menyusun dokumen perusahaan di Indonesia.

Susun dokumen berikut dalam Bahasa Indonesia yang baku, jelas, dan siap dipakai.
Jenis dokumen: {$category->name} ({$category->code}).
{$template['guidance']}

Judul: {$draft->title}
{$context}

Informasi dari penyusun:
{$answers}
{$fromRequest}
Aturan:
- Gunakan informasi dari penyusun sebagai dasar. Lengkapi bagian yang tidak diisi dengan isi yang wajar dan umum untuk jenis dokumen ini, tanpa mengarang nama orang, nomor peraturan yang tidak pasti, atau angka yang tidak masuk akal.
- Tulis isi yang spesifik untuk judul ini, bukan kalimat umum yang bisa dipakai di dokumen mana pun.
- Jangan pakai format markdown (tanpa **, #, atau bullet di dalam teks).
- Setiap bagian wajib diisi.

Kembalikan HANYA satu objek JSON dengan kunci berikut:
{$sections}
PROMPT;
    }
}
