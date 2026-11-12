<?php

namespace App\Services;

use App\Models\Training;
use App\Models\TrainingParticipant;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppService
{
    /**
     * Normalize Indonesian phone number to 08xxxxxxxxxx format as expected by WAGHub
     */
    public static function formatPhoneNumber(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Remove non-numeric characters
        $clean = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($clean, '62')) {
            $clean = '0'.substr($clean, 2);
        } elseif (! str_starts_with($clean, '0')) {
            $clean = '0'.$clean;
        }

        return $clean;
    }

    /**
     * Send WhatsApp OTP message to a phone number
     */
    public static function sendOtp(string $phone, string $otp, string $recipientName = 'Pengguna'): array
    {
        $formattedPhone = self::formatPhoneNumber($phone);
        if (! $formattedPhone) {
            return ['status' => false, 'message' => 'Nomor WhatsApp tidak valid atau kosong'];
        }

        $token = config('services.whatsapp.token');

        $appName = config('app.name', 'Modul');
        $message = "*KODE OTP LOGIN - {$appName}*\n\n"
                 ."Halo *{$recipientName}*,\n\n"
                 ."Kode verifikasi (OTP) untuk login ke akun Anda adalah:\n\n"
                 ."*{$otp}*\n\n"
                 ."Kode ini berlaku selama *5 menit*. Jangan bagikan kode ini kepada siapa pun demi keamanan akun Anda.\n\n"
                 .'Terima kasih.';

        // Simulation is only available in development and automated tests.
        if (empty($token)) {
            if (! app()->environment(['local', 'testing'])) {
                Log::error('WAGHub OTP gateway token is not configured.');

                return ['status' => false, 'message' => 'Gateway WhatsApp belum dikonfigurasi. Silakan hubungi administrator.'];
            }

            Log::info("WAGHub OTP Simulated to [{$formattedPhone}] ({$recipientName}): OTP = {$otp}");

            return [
                'status' => true,
                'message' => 'Simulasi sukses (token gateway belum diisi di .env)',
                'simulated' => true,
                'otp' => $otp,
            ];
        }

        try {
            $response = self::post($formattedPhone, $message, 'otp', 'otp', 'otp-'.time(), now()->addMinutes(10));

            if ($response->successful()) {
                Log::info("WAGHub OTP successfully sent to [{$formattedPhone}]");

                return ['status' => true, 'message' => 'Kode OTP berhasil dikirim ke WhatsApp Anda'];
            } else {
                $errJson = $response->json();
                $errorMsg = $errJson['message'] ?? ('HTTP Error '.$response->status());

                // If 502 / upstream gateway down, provide friendly dev fallback so local testing is never blocked
                if ($response->status() == 502) {
                    $errorMsg = 'Gateway 502 (Server WAGHub sedang sibuk/offline)';
                    if (app()->environment(['local', 'testing'])) {
                        Log::warning("WAGHub OTP Gateway 502. Fallback to local log. OTP for [{$formattedPhone}]: {$otp}");

                        return [
                            'status' => true,
                            'message' => 'Kode OTP dibuat (Server WAGHub 502, kode dikirim via simulasi log)',
                            'dev_otp' => $otp,
                            'simulated' => true,
                        ];
                    }
                }

                Log::error('WAGHub OTP error: '.$errorMsg, ['response' => $response->body()]);

                return ['status' => false, 'message' => 'Gagal mengirim OTP: '.$errorMsg];
            }
        } catch (\Throwable $th) {
            Log::error('WAGHub OTP exception: '.$th->getMessage());

            if (app()->environment(['local', 'testing'])) {
                Log::warning("WAGHub Exception fallback. OTP for [{$formattedPhone}]: {$otp}");

                return [
                    'status' => true,
                    'message' => 'Kode OTP dibuat (Koneksi gateway gagal, kode dikirim via simulasi log)',
                    'dev_otp' => $otp,
                    'simulated' => true,
                ];
            }

            $errorMsg = $th->getMessage();
            if (str_contains($errorMsg, 'cURL error 28') || str_contains($errorMsg, 'timed out')) {
                $errorMsg = 'Koneksi ke gateway WhatsApp timeout. Silakan coba sesaat lagi.';
            }

            return ['status' => false, 'message' => 'Koneksi ke gateway WhatsApp gagal: '.$errorMsg];
        }
    }

    /**
     * Build WhatsApp message text for a training invitation
     */
    public static function buildInvitationMessage(Training $training, TrainingParticipant $participant): string
    {
        $user = $participant->user;
        $trainerName = $training->trainer ? $training->trainer->nama_lengkap : 'Pemateri';
        $dateFormatted = Carbon::parse($training->training_date)->isoFormat('dddd, D MMMM Y');
        $timeFormatted = substr($training->start_time, 0, 5).' - '.substr($training->end_time, 0, 5).' WIB';

        $portalUrl = url("/training/portal/{$participant->token}");

        $msg = "*PEMBERITAHUAN JADWAL PELATIHAN*\n\n";
        $msg .= "Yth. Rekan *{$user->nama_lengkap}*,\n\n";
        $msg .= "Anda dijadwalkan untuk mengikuti sesi pelatihan internal dengan rincian berikut:\n\n";
        $msg .= "Topik: {$training->title}\n";
        $msg .= "Pemateri: {$trainerName}\n";
        $msg .= "Hari/Tanggal: {$dateFormatted}\n";
        $msg .= "Waktu: {$timeFormatted}\n";
        // Pelatihan tatap muka tidak punya tautan meeting.
        $msg .= $training->zoom_link ? "Link Zoom/Meeting: {$training->zoom_link}\n\n" : "\n";
        $msg .= 'Untuk '.self::portalActivities($training).", silakan buka tautan berikut:\n";
        $msg .= "{$portalUrl}\n\n";
        $msg .= 'Mohon hadir tepat waktu. Terima kasih atas perhatian dan kerja samanya.';

        return $msg;
    }

    /**
     * What the participant does on the portal for this training, e.g. "melakukan konfirmasi kehadiran dan pengerjaan
     * kuis evaluasi". Only what the training actually has is mentioned (attendance, pretest, posttest, evaluation).
     */
    private static function portalActivities(Training $training): string
    {
        $quiz = match (true) {
            $training->has_pretest && $training->has_posttest => 'pengerjaan pretest dan posttest',
            (bool) $training->has_pretest => 'pengerjaan pretest',
            (bool) $training->has_posttest => 'pengerjaan kuis evaluasi',
            default => null,
        };

        $activities = array_filter([
            $training->has_attendance ? 'konfirmasi kehadiran' : null,
            $quiz,
            $training->has_evaluation ? 'pengisian form evaluasi' : null,
        ]);

        // Pelatihan yang hanya sesi, tanpa presensi: portal tetap memuat rincian dan materinya.
        return $activities === [] ? 'melihat rincian pelatihan' : 'melakukan '.Arr::join($activities, ', ', ' dan ');
    }

    /**
     * Send WhatsApp message to single participant via WAGHub API
     */
    public static function sendToParticipant(TrainingParticipant $participant): array
    {
        $user = $participant->user;
        $phone = self::formatPhoneNumber($user->no_hp ?? '');

        if (! $phone) {
            $participant->update([
                'wa_status' => 'Gagal: Nomor WA kosong',
            ]);

            return ['status' => false, 'message' => 'Nomor WA tidak valid atau kosong'];
        }

        $training = $participant->training;
        if (! $training) {
            $participant->update([
                'wa_status' => 'Gagal: Data pelatihan tidak ditemukan',
            ]);

            return ['status' => false, 'message' => 'Data pelatihan tidak ditemukan'];
        }

        $message = self::buildInvitationMessage($training, $participant);

        $token = config('services.whatsapp.token');

        // If no token configured, simulate and log
        if (empty($token)) {
            Log::info("WAGHub Simulated to [{$phone}] ({$user->nama_lengkap}):\n".$message);
            $participant->update([
                'wa_sent_at' => now(),
                'wa_status' => 'Terkirim (Simulasi / Log)',
            ]);

            return ['status' => true, 'message' => 'Simulasi sukses (token belum diisi)'];
        }

        try {
            $response = self::post(
                $phone,
                $message,
                'otp',
                'trn-'.$training->id.'-'.$participant->id,
                'trn-'.$training->id.'-'.$participant->id,
                now()->addDays(7),
            );

            if ($response->successful()) {
                $participant->update([
                    'wa_sent_at' => now(),
                    'wa_status' => 'Terkirim',
                ]);

                return ['status' => true, 'message' => 'Pesan WA berhasil dikirim via WAGHub'];
            } else {
                $errJson = $response->json();
                $errorMsg = $errJson['message'] ?? ('HTTP Error '.$response->status());

                // If 502 / upstream down, record informative status
                if ($response->status() == 502) {
                    $errorMsg = 'Gateway 502 (Server WAGHub sedang sibuk/offline)';
                }

                $participant->update([
                    'wa_status' => 'Gagal: '.substr($errorMsg, 0, 100),
                ]);

                return ['status' => false, 'message' => 'Gagal WAGHub: '.$errorMsg];
            }
        } catch (\Throwable $th) {
            Log::error('WAGHub send exception: '.$th->getMessage());
            $errorMsg = $th->getMessage();
            if (str_contains($errorMsg, 'cURL error 28') || str_contains($errorMsg, 'timed out')) {
                $errorMsg = 'Koneksi ke gateway WhatsApp timeout (server gateway sedang lambat/tidak terjangkau). Coba kirim ulang beberapa saat lagi.';
            }
            $participant->update([
                'wa_status' => 'Error: '.substr($errorMsg, 0, 100),
            ]);

            return ['status' => false, 'message' => $errorMsg];
        }
    }

    /**
     * Kirim pesan teks bebas (mis. notifikasi proses selesai). Tanpa token, pesan hanya dicatat di log saat
     * local/testing.
     *
     * @return array{status: bool, message: string, simulated?: bool}
     */
    public static function sendText(string $phone, string $text, string $reference = 'notif'): array
    {
        $formattedPhone = self::formatPhoneNumber($phone);
        if (! $formattedPhone) {
            return ['status' => false, 'message' => 'Nomor WhatsApp tidak valid atau kosong'];
        }

        if (empty(config('services.whatsapp.token'))) {
            if (! app()->environment(['local', 'testing'])) {
                Log::error('WAGHub gateway token is not configured.');

                return ['status' => false, 'message' => 'Gateway WhatsApp belum dikonfigurasi.'];
            }

            Log::info("WAGHub Simulated to [{$formattedPhone}]:
".$text);

            return ['status' => true, 'message' => 'Simulasi sukses (token gateway belum diisi di .env)', 'simulated' => true];
        }

        try {
            // Purpose 'otp' sama seperti undangan pelatihan: rute pengiriman yang sudah diizinkan di WAGHub.
            $response = self::post($formattedPhone, $text, 'otp', $reference, $reference, now()->addDay());
        } catch (\Throwable $th) {
            Log::error('WAGHub send exception: '.$th->getMessage());

            return ['status' => false, 'message' => $th->getMessage()];
        }

        if ($response->successful()) {
            return ['status' => true, 'message' => 'Pesan WA berhasil dikirim'];
        }

        $errorMsg = $response->json('message') ?? ('HTTP Error '.$response->status());
        Log::error('WAGHub send error: '.$errorMsg, ['response' => $response->body()]);

        return ['status' => false, 'message' => $errorMsg];
    }

    /**
     * Satu panggilan ke endpoint pesan WAGHub. Pemanggil yang menafsirkan respons dan menangani exception.
     */
    private static function post(
        string $phone,
        string $text,
        string $purpose,
        string $keyPrefix,
        string $clientReference,
        Carbon $expiresAt,
    ): Response {
        $rawUrl = (string) config('services.whatsapp.url');
        $baseUrl = preg_match('/^https?:\/\//i', $rawUrl) ? $rawUrl : 'https://'.$rawUrl;
        $endpoint = rtrim($baseUrl, '/').'/api/v1/messages';
        $idempotencyKey = $keyPrefix.'-'.$phone.'-'.time().'-'.Str::random(4);

        return Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.config('services.whatsapp.token'),
            'Idempotency-Key' => $idempotencyKey,
            'Content-Type' => 'application/json',
        ])->withOptions([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ])->connectTimeout(5)->timeout(15)->retry(2, 500)->post($endpoint, [
            'recipient' => [
                'type' => 'phone',
                'value' => $phone,
            ],
            'message' => [
                'type' => 'text',
                'text' => $text,
            ],
            'purpose' => $purpose,
            'mode' => 'sync',
            'route_key' => 'default',
            'idempotency_key' => $idempotencyKey,
            'expires_at' => $expiresAt->toIso8601String(),
            'client_reference' => $clientReference,
        ]);
    }
}
