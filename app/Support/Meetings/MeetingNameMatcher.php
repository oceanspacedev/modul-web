<?php

namespace App\Support\Meetings;

/**
 * Mencocokkan nama tampilan di meeting (mis. "M. Rizky (Host)", "01_Budi") dengan daftar peserta resmi pelatihan.
 * Dipakai hasil pindaian screenshot AI dan data kamera dari monitoring meeting.
 */
final class MeetingNameMatcher
{
    public const MIN_SCORE = 65;

    /**
     * Peserta yang paling mirip, atau null bila tidak ada yang mencapai MIN_SCORE.
     *
     * @param  array<int, array{id: int, name: string, id_karyawan?: ?string}>  $participants
     * @return array{id: int, name: string, id_karyawan: string, score: int}|null
     */
    public static function match(string $meetingName, array $participants): ?array
    {
        $bestMatch = null;
        $highestScore = 0.0;
        $highestTokens = 0;

        $cleanMeeting = self::sanitize($meetingName);
        $meetingTokens = array_values(array_filter(explode(' ', $cleanMeeting), fn ($t) => strlen($t) > 0));

        if ($meetingTokens === []) {
            return null;
        }

        foreach ($participants as $p) {
            $cleanOfficial = self::sanitize((string) $p['name']);
            $officialTokens = array_values(array_filter(explode(' ', $cleanOfficial), fn ($t) => strlen($t) > 0));

            if ($cleanMeeting === $cleanOfficial) {
                return [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'id_karyawan' => $p['id_karyawan'] ?? '-',
                    'score' => 100,
                ];
            }

            similar_text($cleanMeeting, $cleanOfficial, $percent);

            $meetingCoverage = self::coverage($meetingTokens, $officialTokens);
            if ($meetingCoverage === null) {
                continue;
            }

            $combinedScore = max(round(($percent * 0.3) + ($meetingCoverage * 0.7)), self::containedScore($officialTokens, $meetingTokens));

            // Skor sama: nama resmi yang lebih lengkap menang ("Ahmad Fauzi" atas "Ahmad" untuk "Ahmad Fauzi TINTP").
            if ($combinedScore > $highestScore || ($combinedScore === $highestScore && count($officialTokens) > $highestTokens)) {
                $highestScore = $combinedScore;
                $highestTokens = count($officialTokens);
                $bestMatch = [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'id_karyawan' => $p['id_karyawan'] ?? '-',
                    'score' => (int) $highestScore,
                ];
            }
        }

        return $highestScore >= self::MIN_SCORE ? $bestMatch : null;
    }

    /**
     * Nama resmi minimal dua kata yang tertulis utuh di nama meeting beserta tambahan, mis. "YAYAT RUHIYATNA CS
     * GEBANG" atau "Siti Aminah TINTP": 85, berkurang 5 untuk setiap kata tambahan. Nama resmi satu kata tidak
     * memakai aturan ini, karena "Budi" tidak boleh otomatis cocok dengan "Budi Santoso".
     *
     * @param  list<string>  $officialTokens
     * @param  list<string>  $meetingTokens
     */
    private static function containedScore(array $officialTokens, array $meetingTokens): float
    {
        $official = array_values(array_filter($officialTokens, fn ($token) => ! is_numeric($token)));
        $meeting = array_values(array_filter($meetingTokens, fn ($token) => ! is_numeric($token)));

        if (count($official) < 2 || array_diff($official, $meeting) !== []) {
            return 0;
        }

        return (float) max(0, 85 - 5 * (count($meeting) - count($official)));
    }

    /**
     * Persentase token $tokens yang ditemukan di $others (sama persis, awalan minimal 3 huruf, atau inisial). Null
     * bila $tokens hanya berisi angka seperti nomor urut "01".
     *
     * @param  list<string>  $tokens
     * @param  list<string>  $others
     */
    private static function coverage(array $tokens, array $others): ?float
    {
        $matchedWeight = 0;
        $counted = 0;

        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                continue;
            }
            $counted++;

            foreach ($others as $other) {
                if ($token === $other) {
                    $matchedWeight += 1.0;
                    break;
                } elseif (strlen($token) >= 3 && (str_starts_with($other, $token) || str_starts_with($token, $other))) {
                    $matchedWeight += 0.9;
                    break;
                } elseif (strlen($token) === 1 && str_starts_with($other, $token)) {
                    // Inisial seperti "m" untuk "muhammad".
                    $matchedWeight += 0.85;
                    break;
                }
            }
        }

        return $counted === 0 ? null : ($matchedWeight / $counted) * 100;
    }

    /** Huruf kecil tanpa label platform seperti "(Host)" dan tanda baca. */
    public static function sanitize(string $name): string
    {
        $clean = preg_replace('/\((.*?)\)/', '', $name);
        $clean = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $clean);

        return strtolower(trim(preg_replace('/\s+/', ' ', $clean)));
    }
}
