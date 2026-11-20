<?php

namespace App\Support;

/**
 * Merapikan isi Dokumen Builder (dari AI maupun dari editor) agar selalu mengikuti bagian template: hanya bagian yang
 * dikenal, tipe data sesuai jenis bagian, teks dipangkas, dan jumlah butir dibatasi.
 */
final class DocumentBuilderContent
{
    public const MAX_TEXT = 10000;

    public const MAX_POINT = 1000;

    public const MAX_TITLE = 250;

    public const MAX_ITEMS = 60;

    /**
     * @param  list<array{key: string, type: string}>  $sections
     * @return array<string, string|list<string>|list<array{title: string, text: string}>>
     */
    public static function normalize(array $sections, mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $content = [];

        foreach ($sections as $section) {
            $value = $raw[$section['key']] ?? null;

            $content[$section['key']] = match ($section['type']) {
                'text' => self::text($value),
                'list' => self::points($value),
                default => self::items($value),
            };
        }

        return $content;
    }

    /** Isi kosong untuk semua bagian template. */
    public static function blank(array $sections): array
    {
        return self::normalize($sections, []);
    }

    public static function isEmpty(array $content): bool
    {
        foreach ($content as $value) {
            if ($value !== '' && $value !== []) {
                return false;
            }
        }

        return true;
    }

    private static function text(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode("\n", array_filter(array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value)));
        }

        return self::clean(is_scalar($value) ? (string) $value : '', self::MAX_TEXT);
    }

    /** @return list<string> */
    private static function points(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\R/', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $points = [];
        foreach ($value as $point) {
            if (is_array($point)) {
                $point = trim(($point['title'] ?? '').(filled($point['text'] ?? null) ? ': '.$point['text'] : ''), ': ');
            }
            $point = self::clean(is_scalar($point) ? preg_replace('/^\s*(?:[-•*]|\d+[.)])\s+/u', '', (string) $point) : '', self::MAX_POINT);
            if ($point !== '') {
                $points[] = $point;
            }
        }

        return array_slice($points, 0, self::MAX_ITEMS);
    }

    /** @return list<array{title: string, text: string}> */
    private static function items(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\R/', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                // "Peran: tanggung jawab" dari AI atau tempelan teks.
                [$title, $text] = array_pad(explode(':', (string) $item, 2), 2, '');
                $item = ['title' => $title, 'text' => $text];
            }

            if (! is_array($item)) {
                continue;
            }

            $title = self::clean((string) ($item['title'] ?? $item['label'] ?? $item['name'] ?? ''), self::MAX_TITLE);
            $text = self::clean(is_array($item['text'] ?? null) ? implode("\n", $item['text']) : (string) ($item['text'] ?? $item['description'] ?? ''), self::MAX_POINT * 3);

            if ($title !== '' || $text !== '') {
                $items[] = ['title' => $title, 'text' => $text];
            }
        }

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    private static function clean(string $value, int $max): string
    {
        $value = trim(preg_replace("/[ \t]+/u", ' ', str_replace("\r\n", "\n", $value)) ?? '');

        return mb_substr($value, 0, $max);
    }
}
