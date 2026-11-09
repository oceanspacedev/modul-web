<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Hasil import/export yang diproses di belakang layar. Selalu masuk ke menu Notifikasi, lalu dikirim juga ke
 * email dan WhatsApp bila user punya alamat/nomornya.
 */
class BackgroundTaskFinished extends Notification implements ShouldQueue
{
    use Queueable;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    /** Batas baris error yang disimpan, supaya data notifikasi tidak membengkak. */
    public const MAX_ERRORS = 200;

    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public string $kind,
        public string $status,
        public string $title,
        public string $message,
        public array $errors = [],
        public ?string $downloadPath = null,
        public ?string $downloadName = null,
    ) {
        $this->errors = array_slice(array_values($errors), 0, self::MAX_ERRORS);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            'database',
            filled($notifiable->email ?? null) ? 'mail' : null,
            filled($notifiable->no_hp ?? null) ? WhatsAppChannel::class : null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'status' => $this->status,
            'title' => $this->title,
            'message' => $this->message,
            'errors' => $this->errors,
            'error_count' => count($this->errors),
            'download_path' => $this->downloadPath,
            'download_name' => $this->downloadName,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title.' - '.config('app.name'))
            ->greeting('Halo '.($notifiable->nama_lengkap ?? $notifiable->username ?? '').',')
            ->line($this->message);

        if ($this->status === self::STATUS_FAILED) {
            $mail->error();
        }

        if ($this->errors !== []) {
            $mail->line('Ada '.count($this->errors).' baris yang perlu diperiksa. Rinciannya ada di menu Notifikasi.');
        }

        if ($this->downloadPath !== null) {
            $mail->line('File hasil export bisa diunduh dari menu Notifikasi selama 7 hari.');
        }

        return $mail->action('Buka Notifikasi', route('notifications.index'));
    }

    public function toWhatsApp(object $notifiable): string
    {
        $text = '*'.mb_strtoupper($this->title).' - '.config('app.name')."*\n\n"
            .'Halo *'.($notifiable->nama_lengkap ?? $notifiable->username ?? '')."*,\n\n"
            .$this->message."\n\n";

        if ($this->errors !== []) {
            $text .= 'Ada '.count($this->errors)." baris yang perlu diperiksa.\n\n";
        }

        if ($this->downloadPath !== null) {
            $text .= "File hasil export bisa diunduh selama 7 hari.\n\n";
        }

        return $text.'Lihat rinciannya di menu Notifikasi: '.route('notifications.index');
    }
}
