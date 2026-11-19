<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestRecipient;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Kabar ke pengelola Permintaan Dokumen bahwa ada pengajuan baru. Dikirim ke nomor WhatsApp pengelola yang diatur di
 * halaman Kategori Dokumen; bila belum ada, ke nomor admin OD di config od-report.admin_whatsapp.
 */
class DocumentRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $text) {}

    public static function sendToManagers(DocumentRequest $request): void
    {
        $numbers = DocumentRequestRecipient::pluck('whatsapp');
        if ($numbers->isEmpty() && filled(config('od-report.admin_whatsapp'))) {
            $numbers = collect([config('od-report.admin_whatsapp')]);
        }
        if ($numbers->isEmpty()) {
            return;
        }

        $notification = new self(self::message($request));
        $numbers->each(fn (string $number) => NotificationFacade::route('whatsapp', $number)->notify($notification));
    }

    /**
     * Teks disusun saat pengajuan dibuat, supaya isi pesan sama untuk semua pengelola.
     */
    public static function message(DocumentRequest $request): string
    {
        $request->loadMissing(['category', 'processes']);

        $lines = [
            "*Pengajuan Dokumen Baru {$request->code}*",
            '',
            'Kategori: '.$request->category?->label(),
            "Pemohon: {$request->nama} ({$request->jabatan} · {$request->departemen})",
            "WA {$request->whatsapp} · {$request->email}",
            '',
            ...$request->processes->values()->map(fn ($process, $i) => ($i + 1).". {$process->title} ({$process->prioritas})")->all(),
        ];

        if ($request->obs_tanggal_1) {
            $dates = collect([$request->obs_tanggal_1, $request->obs_tanggal_2])->filter()
                ->map(fn ($date) => $date->locale('id')->translatedFormat('l, d F Y'))->implode(' / ');
            $lines[] = '';
            $lines[] = "Observasi: {$dates}";
        }

        $lines[] = '';
        $lines[] = 'Buka: '.route('od-report.requests.show', $request);

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->text;
    }
}
