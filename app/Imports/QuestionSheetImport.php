<?php

namespace App\Imports;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Dasar import soal dari Excel/CSV (pelatihan dan modul belajar). Tujuannya tidak pernah menebak:
 * - kolom dibaca dari judulnya di baris pertama, bukan dari urutannya, jadi kolom yang digeser atau file tanpa judul
 *   ditolak alih-alih terbaca sebagai data yang salah;
 * - baris yang isinya tidak lengkap atau bermakna ganda (kunci tidak menunjuk pilihan, pilihan kembar, tipe tidak
 *   dikenal, pertanyaan kosong) menggagalkan import dengan nomor barisnya.
 * Hanya sheet pertama yang dibaca (FirstSheetOnly), dan seluruh file diimpor dalam satu transaksi oleh
 * ProcessQuestionImport, jadi satu baris salah membatalkan semuanya.
 */
abstract class QuestionSheetImport implements ToCollection
{
    protected int $importedCount = 0;

    /**
     * Judul kolom yang wajib ada di baris pertama.
     *
     * @return list<string>
     */
    abstract protected function requiredColumns(): array;

    /**
     * Judul kolom yang boleh ada. Kolom lain (mis. "catatan" dari file export) diabaikan.
     *
     * @return list<string>
     */
    abstract protected function optionalColumns(): array;

    /**
     * Simpan satu baris soal. $row berisi teks tiap kolom yang dikenal ('' bila kosong atau kolomnya tidak ada).
     *
     * @param  array<string, string>  $row
     */
    abstract protected function importRow(array $row, int $rowNumber): void;

    public function collection(Collection $rows): void
    {
        $columns = $this->columnPositions($rows->first() ?? collect());
        $known = [...$this->requiredColumns(), ...$this->optionalColumns()];

        foreach ($rows->slice(1)->values() as $index => $cells) {
            $rowNumber = $index + 2;

            $row = [];
            foreach ($known as $name) {
                $row[$name] = isset($columns[$name]) ? self::text($cells[$columns[$name]] ?? null) : '';
            }

            // Baris kosong (mis. sisa format di bawah data) dilewati; baris berisi sebagian harus lengkap.
            if (implode('', $row) === '') {
                continue;
            }

            $this->importRow($row, $rowNumber);
            $this->importedCount++;
        }

        if ($this->importedCount === 0) {
            throw new Exception('File tidak berisi soal: tidak ada baris terisi di bawah judul kolom.');
        }
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * Posisi tiap kolom yang dikenal, dari judul di baris pertama.
     *
     * @return array<string, int>
     */
    private function columnPositions(Collection $header): array
    {
        $positions = [];
        foreach ($header as $position => $title) {
            $name = Str::of(self::text($title))->lower()->replaceMatches('/[\s\-]+/', '_')->toString();
            if ($name === '') {
                continue;
            }
            if (isset($positions[$name])) {
                throw new Exception("Judul kolom \"{$name}\" muncul dua kali di baris pertama. Sisakan satu saja.");
            }
            $positions[$name] = $position;
        }

        $missing = array_values(array_diff($this->requiredColumns(), array_keys($positions)));
        if ($missing !== []) {
            throw new Exception(
                'Baris pertama file harus berisi judul kolom seperti di template. Kolom yang tidak ditemukan: '
                .implode(', ', $missing).'. Unduh template, lalu salin data Anda ke kolom yang sesuai.'
            );
        }

        return $positions;
    }

    /**
     * Isi sel sebagai teks. Excel menyimpan sebagian isian bukan sebagai teks: angka (0, 10, 0.5) dan TRUE/FALSE.
     * Semuanya dikembalikan ke tulisan yang terlihat di sel, jadi pilihan "0" atau "FALSE" tidak dianggap kosong.
     */
    protected static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            is_float($value) && floor($value) === $value && abs($value) < 1e15 => (string) (int) $value,
            default => trim((string) $value),
        };
    }

    /**
     * Pilihan jawaban yang terisi, per huruf. Menolak pilihan A/B yang kosong dan dua pilihan berisi teks sama
     * (peserta dan kunci jawabannya jadi bermakna ganda).
     *
     * @param  array<string, string>  $row
     * @param  list<string>  $letters
     * @return array<string, string>
     */
    protected function options(array $row, array $letters, int $rowNumber): array
    {
        $options = [];
        foreach ($letters as $letter) {
            if ($row['pilihan_'.$letter] !== '') {
                $options[$letter] = $row['pilihan_'.$letter];
            }
        }

        foreach (['a', 'b'] as $letter) {
            if (! isset($options[$letter])) {
                throw new Exception("Baris ke-{$rowNumber}: soal pilihan ganda harus punya minimal Pilihan A dan Pilihan B; Pilihan ".strtoupper($letter).' kosong.');
            }
        }

        $seen = [];
        foreach ($options as $letter => $content) {
            $normalized = Str::lower(Str::squish($content));
            if (isset($seen[$normalized])) {
                throw new Exception("Baris ke-{$rowNumber}: Pilihan ".strtoupper($seen[$normalized]).' dan '.strtoupper($letter)." berisi teks yang sama (\"{$content}\"). Bedakan isinya atau kosongkan salah satunya.");
            }
            $seen[$normalized] = $letter;
        }

        return $options;
    }

    /**
     * Huruf kunci jawaban. Kunci berupa satu huruf selalu dibaca sebagai huruf pilihan dan pilihannya harus terisi;
     * selain itu kunci boleh berupa teks jawaban lengkap yang sama persis dengan salah satu pilihan.
     *
     * @param  array<string, string>  $options
     * @param  list<string>  $letters
     */
    protected function keyLetter(string $rawKey, array $options, array $letters, int $rowNumber): string
    {
        $range = strtoupper($letters[0]).'-'.strtoupper(end($letters));

        if ($rawKey === '') {
            throw new Exception("Baris ke-{$rowNumber}: kunci_jawaban kosong. Isi dengan huruf pilihan yang benar ({$range}).");
        }

        $letter = strtolower($rawKey);
        if (in_array($letter, $letters, true)) {
            if (! isset($options[$letter])) {
                throw new Exception("Baris ke-{$rowNumber}: kunci_jawaban \"{$rawKey}\" menunjuk Pilihan ".strtoupper($letter).' yang kosong.');
            }

            return $letter;
        }

        $matches = array_keys(array_filter($options, fn (string $content) => Str::lower(Str::squish($content)) === Str::lower(Str::squish($rawKey))));
        if (count($matches) !== 1) {
            throw new Exception("Baris ke-{$rowNumber}: kunci_jawaban \"{$rawKey}\" bukan huruf pilihan ({$range}) dan tidak sama dengan teks salah satu pilihan.");
        }

        return $matches[0];
    }
}
