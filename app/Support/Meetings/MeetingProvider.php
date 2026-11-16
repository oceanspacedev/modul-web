<?php

namespace App\Support\Meetings;

use App\Models\MeetingAccount;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Satu platform meeting (Zoom, Google Meet, Microsoft Teams, ...). Setiap platform mengenali tautannya sendiri;
 * kemampuan tambahan (pemantauan langsung, impor chat) hanya diisi oleh platform yang integrasinya tersedia.
 * Menambah platform baru berarti menambah satu kelas turunan dan mendaftarkannya di MeetingProviders.
 */
abstract class MeetingProvider
{
    /** Nilai yang disimpan di trainings.meeting_platform dan kolom provider lainnya. */
    abstract public function key(): string;

    abstract public function label(): string;

    /** @param  string  $host  Host tautan, huruf kecil. */
    abstract public function matchesHost(string $host): bool;

    /** Kode rapat pada tautan gabung, atau null bila tautannya tidak memuat kode yang dikenali. */
    public function meetingCode(?string $link): ?string
    {
        return null;
    }

    /**
     * Kredensial akun penyelenggara yang dibutuhkan integrasi platform ini. Kosong berarti belum ada integrasi.
     * "multiline" untuk nilai panjang (mis. berkas kunci JSON), "max" batas panjangnya (bawaan 512), "help"
     * petunjuk singkat di bawah kolom.
     *
     * @return array<string, array{label: string, secret: bool, multiline?: bool, max?: int, help?: string}>
     */
    public function credentialFields(): array
    {
        return [];
    }

    /**
     * Pemeriksaan isi kredensial di luar "wajib diisi", per kolom.
     *
     * @param  array<string, string>  $credentials
     * @return array<string, string>
     */
    public function credentialErrors(array $credentials): array
    {
        return [];
    }

    /** Platform ini punya integrasi yang memakai akun penyelenggara (pemantauan langsung atau tarik kehadiran). */
    public function hasIntegration(): bool
    {
        return $this->supportsMonitoring() || $this->supportsAttendanceSync();
    }

    /** Petugas dapat bergabung dari halaman pelatihan dan aktivitas peserta dicatat selama rapat berjalan. */
    public function supportsMonitoring(): bool
    {
        return false;
    }

    /** Jam masuk dan keluar peserta dapat ditarik dari API platform (di server, tanpa bergabung ke rapat). */
    public function supportsAttendanceSync(): bool
    {
        return false;
    }

    public function supportsChatImport(): bool
    {
        return false;
    }

    /**
     * Aktivitas peserta rapat menurut catatan platform. "key" harus tetap sama untuk kejadian yang sama pada
     * setiap penarikan, supaya penarikan ulang tidak menggandakan data.
     *
     * @return list<array{key: string, type: string, observed_at: Carbon, participant_key: string, name: string, camera_on: ?bool}>
     */
    public function fetchAttendance(MeetingAccount $account, string $link, string $meetingCode): array
    {
        throw new LogicException("Penarikan kehadiran {$this->label()} belum tersedia.");
    }

    /**
     * Data yang dibutuhkan browser untuk bergabung ke rapat sebagai pemantau. Tidak boleh memuat rahasia akun.
     *
     * @return array<string, mixed>
     */
    public function joinPayload(MeetingAccount $account, string $meetingCode): array
    {
        throw new LogicException("Pemantauan {$this->label()} belum tersedia.");
    }

    /**
     * Pesan publik dari file chat yang diekspor platform ini.
     *
     * @return list<array{sent_time: string, sender: string, message: string, fingerprint: string}>
     */
    public function parseChat(string $contents): array
    {
        throw new LogicException("Impor chat {$this->label()} belum tersedia.");
    }

    /** Bagian tautan yang sudah dipastikan https, tanpa kredensial dan port, atau null. */
    protected function joinUrlParts(?string $link): ?array
    {
        $parts = parse_url($link ?? '');

        if (! is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user'])
            || isset($parts['port'])
            || ! $this->matchesHost(strtolower($parts['host'] ?? ''))) {
            return null;
        }

        return $parts;
    }
}
