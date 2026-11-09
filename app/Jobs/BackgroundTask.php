<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\BackgroundTaskFinished;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Dasar import/export berat yang diproses di belakang layar.
 *
 * Berjalan di queue "heavy" pada koneksi berbatas waktu panjang (redis-long, sama dengan job AI) lalu mengabari
 * user yang memulainya lewat BackgroundTaskFinished. Unik per user dan jenis proses supaya klik dobel tidak
 * memproses file yang sama dua kali.
 */
abstract class BackgroundTask implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const QUEUE = 'heavy';

    /** Pesan flash setelah proses dimasukkan ke antrean. */
    public const QUEUED_MESSAGE = 'File sedang diproses di belakang layar. Hasilnya akan dikirim ke menu Notifikasi, WhatsApp, dan email Anda.';

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(public int $userId)
    {
        $this->onConnection(config('queue.ai_connection'))->onQueue(self::QUEUE);
    }

    /**
     * Kode jenis proses, disimpan di data notifikasi.
     */
    abstract public function kind(): string;

    /**
     * Judul notifikasi saat proses gagal.
     */
    abstract protected function failureTitle(): string;

    /**
     * Pekerjaan utamanya. Mengembalikan notifikasi hasil untuk dikirim ke user.
     */
    abstract protected function process(): BackgroundTaskFinished;

    /**
     * Membersihkan file sementara milik proses ini (dipanggil baik saat berhasil maupun gagal).
     */
    protected function cleanup(): void {}

    public function uniqueId(): string
    {
        return $this->kind().':'.$this->userId;
    }

    public function handle(): void
    {
        try {
            $notification = $this->process();
        } catch (Throwable $e) {
            report($e);
            $notification = $this->failure($e->getMessage());
        } finally {
            $this->cleanup();
        }

        $this->notify($notification);
    }

    /**
     * Worker dihentikan (mis. melewati batas waktu) sebelum handle() sempat mengirim hasil.
     */
    public function failed(?Throwable $exception): void
    {
        $this->cleanup();
        $this->notify($this->failure('Proses terhenti sebelum selesai (melewati batas waktu). Coba lagi dengan file yang lebih kecil.'));
    }

    protected function failure(string $reason): BackgroundTaskFinished
    {
        return new BackgroundTaskFinished($this->kind(), BackgroundTaskFinished::STATUS_FAILED, $this->failureTitle(), $reason);
    }

    protected function notify(BackgroundTaskFinished $notification): void
    {
        User::find($this->userId)?->notify($notification);
    }
}
