<?php

namespace App\Support\Meetings;

use InvalidArgumentException;

final class ZoomPublicChat
{
    // Zoom desktop exports a clock time, sometimes with an ISO date before it.
    private const TIME = '(?:\d{4}-\d{2}-\d{2}\h+)?(?:[01]?\d|2[0-3]):[0-5]\d:[0-5]\d';

    private const RECIPIENTS = ['everyone', 'semua orang', 'semua orang dalam rapat'];

    private const PRIVATE_MARKER = '/\(\h*(?:direct\h+message|privately|pesan\h+langsung|secara\h+privat)\h*\)/iu';

    /** @return list<array{sent_time: string, sender: string, message: string, fingerprint: string}> */
    public static function parse(string $contents): array
    {
        $contents = self::decode($contents);
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $contents));
        $messages = [];
        $occurrences = [];
        $hasZoomHeader = false;

        for ($index = 0, $lineCount = count($lines); $index < $lineCount; $index++) {
            if (! preg_match('/^('.self::TIME.')\h+(From|Dari)\h+([^:\n]+):(.*)$/iu', $lines[$index], $header)) {
                continue;
            }

            $separator = strcasecmp($header[2], 'From') === 0 ? 'to' : 'ke|kepada';
            $address = preg_split('/\h+(?:'.$separator.')\h+/iu', trim($header[3]));
            if (count($address) < 2) {
                continue;
            }

            $hasZoomHeader = true;
            [$sender, $recipient] = $address;
            $isPublic = count($address) === 2
                && in_array(mb_strtolower(trim($recipient)), self::RECIPIENTS, true)
                && preg_match(self::PRIVATE_MARKER, $lines[$index]) === 0;

            $body = trim($header[4]);
            // Read continuous, indented Zoom body lines only. Timestamp-like rows and indentation breaks
            // close the entry even when the next header is ambiguous. Always consume a private entry's body.
            while (isset($lines[$index + 1]) && self::isBodyLine($lines[$index + 1])) {
                $continuation = trim($lines[++$index]);
                if (preg_match(self::PRIVATE_MARKER, $continuation)) {
                    $isPublic = false;
                }

                if ($isPublic) {
                    $body .= ($body === '' ? '' : "\n").$continuation;
                }
            }

            if (! $isPublic || $body === '') {
                continue;
            }

            $sentTime = preg_replace('/\h+/u', ' ', $header[1]);
            $sender = trim($sender);
            if (mb_strlen($sender) > 255 || mb_strlen($body) > 4000) {
                throw new InvalidArgumentException('Ada nama pengirim atau isi chat Zoom yang melebihi batas panjang.');
            }

            // Separate genuinely repeated messages in the same second while making re-imports idempotent.
            $identity = hash('sha256', serialize([$sentTime, $sender, $body]));
            $occurrences[$identity] = ($occurrences[$identity] ?? 0) + 1;
            $messages[] = [
                'sent_time' => $sentTime,
                'sender' => $sender,
                'message' => $body,
                'fingerprint' => hash('sha256', $identity.':'.$occurrences[$identity]),
            ];
        }

        if (! $hasZoomHeader) {
            throw new InvalidArgumentException('Format chat tidak dikenali. Pilih file TXT asli dari Simpan Obrolan di aplikasi Zoom desktop.');
        }

        return $messages;
    }

    private static function isBodyLine(string $line): bool
    {
        $clock = '(?:\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\h+)?\d{1,2}:\d{2}';

        // Full Zoom timestamps and address-like lines can start a separate entry. A short time or
        // ordinary sentence beginning with "Dari" or "From" is part of the message.
        return preg_match('/^(?:\t| {4})\h*\S/u', $line) === 1
            && preg_match('/^\h*(?:'.$clock.':\d{2}(?:\h|$)|'.$clock.'\h+(?:From|Dari)\h+|(?:From|Dari)\h+[^:]+:)/iu', $line) === 0;
    }

    private static function decode(string $contents): string
    {
        $encoding = match (substr($contents, 0, 2)) {
            "\xFF\xFE" => 'UTF-16LE',
            "\xFE\xFF" => 'UTF-16BE',
            default => 'UTF-8',
        };

        if ($encoding !== 'UTF-8') {
            $contents = substr($contents, 2);
        } elseif (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        if (! mb_check_encoding($contents, $encoding)) {
            throw new InvalidArgumentException('File chat Zoom harus menggunakan UTF-8 atau UTF-16 dengan BOM.');
        }

        $text = $encoding === 'UTF-8' ? $contents : mb_convert_encoding($contents, 'UTF-8', $encoding);
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
            throw new InvalidArgumentException('File chat bukan teks yang valid. Pilih file TXT asli dari Zoom.');
        }

        return $text;
    }
}
