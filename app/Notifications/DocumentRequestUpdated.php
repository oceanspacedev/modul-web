<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Kabar ke pemohon Permintaan Dokumen: pengajuan diterima atau tahap proses berubah. WhatsApp dikirim ke nomor yang
 * diisi di form (pemohon publik tidak punya akun); pemohon yang login juga mendapatkannya di menu Notifikasi.
 */
class DocumentRequestUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * $attachments: file yang dikirim sebagai dokumen WhatsApp setelah teksnya.
     *
     * @param  list<array{url: string, filename: string, mime_type: string, caption: string}>  $attachments
     */
    public function __construct(public string $code, public string $message, public array $attachments = []) {}

    /**
     * @param  list<array{url: string, filename: string, mime_type: string, caption: string}>  $attachments
     */
    public static function send(DocumentRequest $request, string $message, array $attachments = []): void
    {
        $notification = new self($request->code, $message, $attachments);

        NotificationFacade::route('whatsapp', $request->whatsapp)->notify($notification);
        $request->user?->notify($notification);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? [WhatsAppChannel::class] : ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'document-request',
            'status' => 'success',
            'title' => "Permintaan dokumen {$this->code}",
            'message' => $this->message,
        ];
    }

    /**
     * @return list<array{url: string, filename: string, mime_type: string, caption: string}>
     */
    public function whatsAppAttachments(): array
    {
        return $this->attachments;
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "*Permintaan Dokumen {$this->code}*\n\n{$this->message}\n\nLacak: ".route('document-request.track', ['kode' => $this->code]);
    }
}
