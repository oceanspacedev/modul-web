<?php

namespace App\Imports;

use App\Models\TrainingQuestion;
use Exception;
use Illuminate\Support\Str;

/**
 * Membaca soal kuis pelatihan dari teks yang ditempel (Import Teks Cepat) menjadi daftar kolom soal siap simpan.
 *
 * Format: tiap soal diawali nomor ("1.", "2)", "Soal 3:") atau dipisah baris kosong; pilihan "A." sampai "F.";
 * baris "Kunci:", "Penjelasan:", dan "Tipe:" (PG / ESSAY). Baris tanpa awalan itu melanjutkan bagian di atasnya.
 *
 * Tidak ada yang ditebak: soal pilihan ganda tanpa kunci yang jelas, pilihan yang tidak lengkap atau kembar, dan
 * pilihan tanpa pertanyaan menggagalkan seluruh import dengan nomor soalnya.
 */
class TrainingQuestionTextParser
{
    private const NUMBERED = '/^(?:Soal\s*\d+\s*[\.\):]|\d+[\.\)])\s*/i';

    private const TYPE = '/^(?:TIPE|TYPE)\s*:\s*(.*)$/i';

    private const KEY = '/^(?:KUNCI(?:\s+JAWABAN)?|JAWABAN|ANSWER|KEY)\s*:\s*(.*)$/i';

    private const EXPLANATION = '/^(?:PENJELASAN|PEMBAHASAN|CATATAN|EXPLANATION|NOTE)\s*:\s*(.*)$/i';

    private const OPTION = '/^([A-Fa-f])[\.\)]\s*(.*)$/';

    private const ESSAY_TYPES = ['essay', 'esai', 'uraian', 'text'];

    private const CHOICE_TYPES = ['pg', 'pilihan ganda', 'multiple choice', 'multiple_choice', 'mc'];

    /**
     * @return list<array<string, mixed>> kolom TrainingQuestion tanpa training_id
     */
    public function parse(string $rawText): array
    {
        $questions = [];
        foreach ($this->blocks($rawText) as $index => $block) {
            $questions[] = $this->question($block, $index + 1);
        }

        return $questions;
    }

    /**
     * Kelompokkan baris menjadi soal. Soal baru dimulai di baris bernomor, atau di baris teks biasa setelah baris
     * kosong. Pilihan, kunci, dan penjelasan setelah baris kosong tetap milik soal di atasnya, jadi baris kosong
     * di antara pertanyaan dan pilihannya tidak memecah soal.
     *
     * @return list<array{question: list<string>, options: array<string, list<string>>, duplicates: list<string>, key: ?list<string>, explanation: ?list<string>, type: ?string}>
     */
    private function blocks(string $rawText): array
    {
        $blocks = [];
        $current = null;
        $last = null;   // bagian yang dilanjutkan baris tanpa awalan: ['question'] | ['options', huruf] | ['key'] | ['explanation']
        $afterGap = true;

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $rawText)) as $line) {
            $line = trim($line);
            if ($line === '') {
                $afterGap = true;

                continue;
            }

            $isField = preg_match(self::TYPE, $line) || preg_match(self::KEY, $line) || preg_match(self::EXPLANATION, $line) || preg_match(self::OPTION, $line);
            if (preg_match(self::NUMBERED, $line) || $current === null || ($afterGap && ! $isField)) {
                if ($current !== null) {
                    $blocks[] = $current;
                }
                $current = ['question' => [], 'options' => [], 'duplicates' => [], 'key' => null, 'explanation' => null, 'type' => null];
                $last = ['question'];
                $line = trim(preg_replace(self::NUMBERED, '', $line));
                $afterGap = false;
                if ($line === '') {
                    continue;
                }
                // Baris pertama soal bisa langsung berupa pilihan/kunci (soal tanpa pertanyaan); tetap dibaca sebagai
                // bagian itu supaya kesalahannya dilaporkan, bukan dianggap teks pertanyaan.
                $isField = preg_match(self::TYPE, $line) || preg_match(self::KEY, $line) || preg_match(self::EXPLANATION, $line) || preg_match(self::OPTION, $line);
                if (! $isField) {
                    $current['question'][] = $line;

                    continue;
                }
            }
            $afterGap = false;

            if (preg_match(self::TYPE, $line, $m)) {
                $current['type'] = trim($m[1]);
            } elseif (preg_match(self::KEY, $line, $m)) {
                $current['key'] = [trim($m[1])];
                $last = ['key'];
            } elseif (preg_match(self::EXPLANATION, $line, $m)) {
                $current['explanation'] = [trim($m[1])];
                $last = ['explanation'];
            } elseif (preg_match(self::OPTION, $line, $m)) {
                $letter = strtolower($m[1]);
                if (isset($current['options'][$letter])) {
                    $current['duplicates'][] = $letter;
                }
                $current['options'][$letter] = [trim($m[2])];
                $last = ['options', $letter];
            } elseif ($last[0] === 'options') {
                $current['options'][$last[1]][] = $line;
            } else {
                $current[$last[0]][] = $line;
            }
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * @param  array{question: list<string>, options: array<string, list<string>>, duplicates: list<string>, key: ?list<string>, explanation: ?list<string>, type: ?string}  $block
     * @return array<string, mixed>
     */
    private function question(array $block, int $number): array
    {
        $join = fn (?array $lines) => $lines === null ? '' : trim(implode(' ', array_filter($lines, fn (string $line) => $line !== '')));
        $text = $join($block['question']);
        $options = array_map($join, $block['options']);
        $key = $join($block['key']);
        $explanation = $join($block['explanation']);

        if ($text === '') {
            throw new Exception("Soal ke-{$number}: ada pilihan atau kunci jawaban tanpa pertanyaan di atasnya. Tulis pertanyaannya tepat sebelum pilihan A.");
        }

        $isEssay = $this->isEssay($block['type'], $options !== [], $number);
        $columns = ['question' => $text, 'explanation' => $explanation !== '' ? $explanation : null];
        $empty = array_fill_keys(array_map(fn (string $letter) => 'option_'.$letter, TrainingQuestion::OPTION_LETTERS), null);

        if ($isEssay) {
            if ($options !== []) {
                throw new Exception("Soal ke-{$number}: ditandai ESSAY tetapi punya pilihan jawaban. Hapus pilihannya, atau hapus baris \"Tipe: Essay\".");
            }

            return [...$columns, ...$empty, 'type' => 'essay', 'correct_answer' => $key !== '' ? $key : null];
        }

        if ($block['duplicates'] !== []) {
            throw new Exception("Soal ke-{$number}: Pilihan ".strtoupper($block['duplicates'][0]).' ditulis dua kali.');
        }

        foreach (['a', 'b'] as $letter) {
            if (! isset($options[$letter])) {
                throw new Exception("Soal ke-{$number}: soal pilihan ganda harus punya minimal Pilihan A dan B; Pilihan ".strtoupper($letter).' tidak ada.');
            }
        }

        $seen = [];
        foreach ($options as $letter => $content) {
            if ($content === '') {
                throw new Exception("Soal ke-{$number}: Pilihan ".strtoupper($letter).' kosong.');
            }
            $normalized = Str::lower(Str::squish($content));
            if (isset($seen[$normalized])) {
                throw new Exception("Soal ke-{$number}: Pilihan ".strtoupper($seen[$normalized]).' dan '.strtoupper($letter)." berisi teks yang sama (\"{$content}\").");
            }
            $seen[$normalized] = $letter;
        }

        return [
            ...$columns,
            ...$empty,
            ...collect($options)->mapWithKeys(fn (string $content, string $letter) => ['option_'.$letter => $content])->all(),
            'type' => 'multiple_choice',
            'correct_answer' => $this->keyLetter($key, $options, $number),
        ];
    }

    /**
     * Tanpa baris "Tipe:", soal yang punya pilihan adalah pilihan ganda dan yang tidak punya adalah essay.
     */
    private function isEssay(?string $rawType, bool $hasOptions, int $number): bool
    {
        if ($rawType === null) {
            return ! $hasOptions;
        }

        $type = strtolower(trim($rawType));

        return match (true) {
            in_array($type, self::ESSAY_TYPES, true) => true,
            in_array($type, self::CHOICE_TYPES, true) && $hasOptions => false,
            in_array($type, self::CHOICE_TYPES, true) => throw new Exception("Soal ke-{$number}: ditandai PG tetapi tidak punya pilihan jawaban."),
            default => throw new Exception("Soal ke-{$number}: tipe \"{$rawType}\" tidak dikenal. Tulis \"Tipe: PG\" atau \"Tipe: Essay\"."),
        };
    }

    /**
     * Kunci boleh ditulis "B", "b.", "(B)", "B. teks pilihan", atau teks pilihannya saja. Kunci yang tidak ada,
     * menunjuk pilihan yang tidak ada, atau teksnya tidak cocok dengan pilihan mana pun ditolak.
     *
     * @param  array<string, string>  $options
     */
    private function keyLetter(string $key, array $options, int $number): string
    {
        if ($key === '') {
            throw new Exception("Soal ke-{$number}: kunci jawaban tidak ditemukan. Tambahkan baris \"Kunci: B\" (huruf pilihan yang benar) di bawah pilihannya.");
        }

        $same = fn (string $a, string $b) => Str::lower(Str::squish($a)) === Str::lower(Str::squish($b));

        if (preg_match('/^\(?([A-Fa-f])\)?[\.\)]?$/', $key, $m) || preg_match('/^\(?([A-Fa-f])\)?[\.\)]\s+(.+)$/', $key, $m)) {
            $letter = strtolower($m[1]);
            if (! isset($options[$letter])) {
                throw new Exception("Soal ke-{$number}: kunci \"{$key}\" menunjuk Pilihan ".strtoupper($letter).' yang tidak ada.');
            }
            if (isset($m[2]) && ! $same($m[2], $options[$letter])) {
                throw new Exception("Soal ke-{$number}: kunci \"{$key}\" menyebut huruf ".strtoupper($letter).' tetapi teksnya berbeda dengan Pilihan '.strtoupper($letter).'.');
            }

            return $letter;
        }

        $matches = array_keys(array_filter($options, fn (string $content) => $same($content, $key)));
        if (count($matches) !== 1) {
            throw new Exception("Soal ke-{$number}: kunci \"{$key}\" bukan huruf pilihan dan tidak sama dengan teks salah satu pilihan.");
        }

        return $matches[0];
    }
}
