<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Dasar proses AI (Gemini) yang hasilnya ditunggu halaman lewat polling, dengan pola yang sama seperti
 * GenerateLearningModuleQuestions: berjalan di koneksi queue AI, hasil ditaruh di cache dengan kunci token.
 *
 * Proses ini membaca file materi/gambar ke memori dan menunggu Gemini sampai beberapa menit, jadi tidak boleh
 * berjalan di dalam request web (batas 128MB, lihat public/index.php). Payload dienkripsi karena bisa membawa API key
 * yang diketik trainer.
 */
abstract class AiTask implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $token)
    {
        $this->onConnection(config('queue.ai_connection'));
    }

    /**
     * Token baru dengan status "pending" di cache; panggil sebelum dispatch.
     */
    public static function newToken(): string
    {
        $token = (string) Str::uuid();
        Cache::put(self::cacheKey($token), ['status' => 'pending'], now()->addHours(2));

        return $token;
    }

    public static function cacheKey(string $token): string
    {
        return 'ai-task:'.$token;
    }

    /**
     * Status untuk polling: pending, done (beserta hasil), atau failed (beserta message). Null bila sudah kedaluwarsa.
     *
     * @return array<string, mixed>|null
     */
    public static function status(string $token): ?array
    {
        return Cache::get(self::cacheKey($token));
    }

    /**
     * Pekerjaan utamanya; hasilnya digabung ke status "done".
     *
     * @return array<string, mixed>
     */
    abstract protected function run(): array;

    /**
     * Membersihkan file sementara milik proses ini (dipanggil baik saat berhasil maupun gagal).
     */
    protected function cleanup(): void {}

    public function handle(): void
    {
        try {
            $this->storeResult(['status' => 'done', ...$this->run()]);
        } catch (Throwable $e) {
            report($e);
            $this->storeResult(['status' => 'failed', 'message' => $e->getMessage()]);
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Worker dihentikan (mis. melewati batas waktu atau memori) sebelum handle() sempat menyimpan hasil.
     */
    public function failed(?Throwable $exception): void
    {
        $this->cleanup();
        $this->storeResult(['status' => 'failed', 'message' => 'Proses AI terhenti sebelum selesai. Coba lagi dengan materi atau gambar yang lebih sedikit.']);
    }

    private function storeResult(array $result): void
    {
        Cache::put(self::cacheKey($this->token), $result, now()->addHours(2));
    }
}
