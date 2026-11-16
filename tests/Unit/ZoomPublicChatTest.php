<?php

namespace Tests\Unit;

use App\Support\Meetings\ZoomPublicChat;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ZoomPublicChatTest extends TestCase
{
    public function test_it_reads_only_explicitly_public_zoom_entries(): void
    {
        $contents = "\xEF\xBB\xBF".implode("\r\n", [
            '09:05:12 From Dwi to Everyone:',
            "\tSelamat pagi!",
            "\tMaterinya sudah siap.",
            '09:05:13 From Dwi to Host (Direct Message):',
            "\tPesan pribadi.",
            "\t09:05:14 From Dwi to Everyone: Kutipan di pesan pribadi.",
            "\tLanjutan pesan pribadi.",
            '09:05:21 From Dwi to Everyone: (Privately) Jangan tampilkan.',
            '09:05:31 From Dwi to Everyone: Palsu (Direct Message): Jangan tampilkan.',
            '2026-11-15 09:05:40 Dari Bayu kepada Semua Orang dalam Rapat: Halo 👋',
            "\t09:05:42 New or unknown entry:",
            "\tIsi dari entri yang tidak dikenali.",
            '09:05:48 From Dwi to Breakout Room 1: Hanya untuk ruang ini.',
        ]);

        $messages = ZoomPublicChat::parse($contents);

        self::assertSame([
            ['09:05:12', 'Dwi', "Selamat pagi!\nMaterinya sudah siap."],
            ['2026-11-15 09:05:40', 'Bayu', 'Halo 👋'],
        ], array_map(fn (array $row) => [$row['sent_time'], $row['sender'], $row['message']], $messages));
    }

    public function test_it_rejects_files_without_an_explicit_local_chat_header(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ZoomPublicChat::parse("00:14:30\tDwi:\tPesan tanpa penerima yang bisa diverifikasi.");
    }

    public function test_it_keeps_public_text_that_starts_with_a_short_time_or_an_ordinary_word(): void
    {
        $messages = ZoomPublicChat::parse(implode("\n", [
            '09:00:00 From Ana to Everyone:',
            "\t09:30 mulai post-test.",
            '09:00:01 From Ana to Everyone:',
            "\tDari tadi audio putus.",
            "\tFrom time to time the sound disappears.",
            "\t09:05:42 New or unknown entry:",
            "\tIsi entri yang tidak dikenali.",
        ]));

        self::assertSame([
            '09:30 mulai post-test.',
            "Dari tadi audio putus.\nFrom time to time the sound disappears.",
        ], array_column($messages, 'message'));
    }

    public function test_it_allows_a_valid_private_only_export_without_retaining_its_contents(): void
    {
        self::assertSame([], ZoomPublicChat::parse("09:05:13 From Dwi to Host (Direct Message):\n\tPesan pribadi."));
    }

    public function test_it_keeps_identical_messages_in_the_same_second_and_recognizes_reimports(): void
    {
        $line = '09:08:00 From Bayu to Everyone: Ya';
        $contents = $line."\n".$line;
        $utf16 = "\xFF\xFE".mb_convert_encoding($contents, 'UTF-16LE', 'UTF-8');

        $messages = ZoomPublicChat::parse($contents);

        self::assertCount(2, $messages);
        self::assertNotSame($messages[0]['fingerprint'], $messages[1]['fingerprint']);
        self::assertSame($messages, ZoomPublicChat::parse($utf16));
    }
}
