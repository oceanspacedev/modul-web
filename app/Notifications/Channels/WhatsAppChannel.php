<?php

namespace App\Notifications\Channels;

use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;

/**
 * Kanal notifikasi WhatsApp lewat WAGHub. Notifikasi yang memakai kanal ini wajib punya toWhatsApp(); bila juga punya
 * whatsAppAttachments(), setiap lampiran dikirim sebagai pesan dokumen setelah teksnya.
 * Kegagalan gateway hanya dicatat di log oleh WhatsAppService supaya kanal lain (email, database) tetap jalan.
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('whatsapp', $notification) ?? $notifiable->no_hp ?? null;

        if (blank($phone) || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        WhatsAppService::sendText($phone, $notification->toWhatsApp($notifiable), 'notif-'.$notification->id);

        if (method_exists($notification, 'whatsAppAttachments')) {
            foreach ($notification->whatsAppAttachments() as $index => $attachment) {
                WhatsAppService::sendDocument($phone, $attachment['caption'], [
                    'url' => $attachment['url'],
                    'filename' => $attachment['filename'],
                    'mime_type' => $attachment['mime_type'],
                ], 'notif-'.$notification->id.'-file-'.$index);
            }
        }
    }
}
