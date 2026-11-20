<?php

namespace App\Support;

use App\Models\DocumentDraft;

/**
 * Ekspor Word draf Dokumen Builder: kop berisi identitas dokumen, judul, lalu bagian-bagian bernomor sesuai template.
 */
final class DocumentDraftWord
{
    public static function save(DocumentDraft $draft, string $path): void
    {
        $word = new DocxWriter;

        if (filled($draft->company)) {
            $word->heading(mb_strtoupper($draft->company), 24, 'center');
        }
        $word->heading(mb_strtoupper($draft->category->name), 28, 'center')
            ->heading($draft->title, 26, 'center')
            ->table(self::identity($draft));

        $number = 0;
        $content = $draft->sections();
        foreach ($draft->template()['sections'] as $section) {
            $value = $content[$section['key']];
            if ($value === '' || $value === []) {
                continue;
            }

            $word->heading((++$number).'. '.$section['label'], 24);

            match ($section['type']) {
                'text' => collect(preg_split('/\n{2,}/', $value))->each(fn ($p) => $word->paragraph($p)),
                'list' => collect($value)->each(fn ($point) => $word->bullet($point)),
                'steps' => collect($value)->each(fn ($item, $i) => $word->item($item['title'], $item['text'], $i + 1)),
                default => collect($value)->each(fn ($item) => $word->item($item['title'], $item['text'])),
            };
        }

        $word->save($path);
    }

    /** @return array<string, string> */
    public static function identity(DocumentDraft $draft): array
    {
        return array_filter([
            'Jenis Dokumen' => $draft->category->label(),
            'Nomor Dokumen' => $draft->document_number,
            'Revisi' => $draft->revision,
            'Tanggal Berlaku' => $draft->effective_date?->translatedFormat('d F Y'),
            'Departemen' => $draft->department,
            'Disahkan oleh' => $draft->approved_by,
        ], fn ($value) => filled($value));
    }
}
