<?php

namespace App\Services;

use App\Models\Training;
use App\Models\TrainingParticipant;
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
        if (!$phone) {
            return null;
        }

        // Remove non-numeric characters
        $clean = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($clean, '62')) {
            $clean = '0' . substr($clean, 2);
        } elseif (!str_starts_with($clean, '0')) {
            $clean = '0' . $clean;
        }

        return $clean;
    }

    /**
     * Send WhatsApp OTP message to a phone number
     */
    public static function sendOtp(string $phone, string $otp, string $recipientName = 'Pengguna'): array
    {
        $formattedPhone = self::formatPhoneNumber($phone);
        if (!$formattedPhone) {
            return ['status' => false, 'message' => 'Nomor WhatsApp tidak valid atau kosong'];
        }

        $rawUrl = env('wag_url') ?: env('WAG_URL') ?: 'waghub.mekayastudio.com';
        $token = env('wag_token') ?: env('WAG_TOKEN');

        if (!preg_match('/^https?:\/\//i', $rawUrl)) {
            $baseUrl = 'https://' . $rawUrl;
        } else {
            $baseUrl = $rawUrl;
        }
        $endpoint = rtrim($baseUrl, '/') . '/api/v1/messages';

        $appName = config('app.name', 'Modul App');
        $message = "*KODE OTP LOGIN - {$appName}*\n\n"
                 . "Halo *{$recipientName}*,\n\n"
                 . "Kode verifikasi (OTP) untuk login ke akun Anda adalah:\n\n"
                 . "*{$otp}*\n\n"
                 . "Kode ini berlaku selama *5 menit*. Jangan bagikan kode ini kepada siapa pun demi keamanan akun Anda.\n\n"
                 . "Terima kasih.";

        // If no token configured, simulate and log
        if (empty($token)) {
            Log::info("WAGHub OTP Simulated to [{$formattedPhone}] ({$recipientName}): OTP = {$otp}");
            return [
                'status' => true,
                'message' => 'Simulasi sukses (token gateway belum diisi di .env)',
                'simulated' => true,
                'otp' => $otp,
            ];
        }

        $idempotencyKey = 'otp-' . $formattedPhone . '-' . time() . '-' . Str::random(4);

        $payload = [
            'recipient' => [
                'type' => 'phone',
                'value' => $formattedPhone,
            ],
            'message' => [
                'type' => 'text',
                'text' => $message,
            ],
            'purpose' => 'otp',
            'mode' => 'sync',
            'route_key' => 'default',
            'idempotency_key' => $idempotencyKey,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
            'client_reference' => 'otp-' . time(),
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'Idempotency-Key' => $idempotencyKey,
                'Content-Type' => 'application/json',
            ])->withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])->connectTimeout(5)->timeout(15)->retry(2, 500)->post($endpoint, $payload);

            if ($response->successful()) {
                Log::info("WAGHub OTP successfully sent to [{$formattedPhone}]");
                return ['status' => true, 'message' => 'Kode OTP berhasil dikirim ke WhatsApp Anda'];
            } else {
                $errJson = $response->json();
                $errorMsg = $errJson['message'] ?? ('HTTP Error ' . $response->status());
                
                // If 502 / upstream gateway down, provide friendly dev fallback so local testing is never blocked
                if ($response->status() == 502) {
                    $errorMsg = 'Gateway 502 (Server WAGHub sedang sibuk/offline)';
                    Log::warning("WAGHub OTP Gateway 502. Fallback to local log. OTP for [{$formattedPhone}]: {$otp}");
                    if (app()->environment('local') || config('app.debug')) {
                        return [
                            'status' => true,
                            'message' => 'Kode OTP dibuat (Server WAGHub 502, kode dikirim via simulasi log)',
                            'dev_otp' => $otp,
                            'simulated' => true,
                        ];
                    }
                }
                
                Log::error("WAGHub OTP error: " . $errorMsg, ['response' => $response->body()]);
                return ['status' => false, 'message' => 'Gagal mengirim OTP: ' . $errorMsg];
            }
        } catch (\Throwable $th) {
            Log::error('WAGHub OTP exception: ' . $th->getMessage());
            
            if (app()->environment('local') || config('app.debug')) {
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
            return ['status' => false, 'message' => 'Koneksi ke gateway WhatsApp gagal: ' . $errorMsg];
        }
    }

    /**
     * Build WhatsApp message text for a training invitation
     */
    public static function buildInvitationMessage(Training $training, TrainingParticipant $participant): string
    {
        $user = $participant->user;
        $trainerName = $training->trainer ? $training->trainer->full_name : 'Pemateri';
        $dateFormatted = \Carbon\Carbon::parse($training->training_date)->isoFormat('dddd, D MMMM Y');
        $timeFormatted = substr($training->start_time, 0, 5) . ' - ' . substr($training->end_time, 0, 5) . ' WIB';
        
        $portalUrl = url("/training/portal/{$participant->token}");

        $msg = "*PEMBERITAHUAN JADWAL PELATIHAN*\n\n";
        $msg .= "Yth. Rekan *{$user->full_name}*,\n\n";
        $msg .= "Anda dijadwalkan untuk mengikuti sesi pelatihan internal dengan rincian berikut:\n\n";
        $msg .= "Topik: {$training->title}\n";
        $msg .= "Pemateri: {$trainerName}\n";
        $msg .= "Hari/Tanggal: {$dateFormatted}\n";
        $msg .= "Waktu: {$timeFormatted}\n";
        $msg .= "Link Zoom/Meeting: {$training->zoom_link}\n\n";
        $msg .= "Untuk melakukan konfirmasi kehadiran dan pengerjaan kuis evaluasi, silakan buka tautan berikut:\n";
        $msg .= "{$portalUrl}\n\n";
        $msg .= "Mohon hadir tepat waktu. Terima kasih atas perhatian dan kerja samanya.";

        return $msg;
    }

    /**
     * Send WhatsApp message to single participant via WAGHub API
     */
    public static function sendToParticipant(TrainingParticipant $participant): array
    {
        $user = $participant->user;
        $phone = self::formatPhoneNumber($user->no_wa ?? '');

        if (!$phone) {
            $participant->update([
                'wa_status' => 'Gagal: Nomor WA kosong',
            ]);
            return ['status' => false, 'message' => 'Nomor WA tidak valid atau kosong'];
        }

        $training = $participant->training;
        if (!$training) {
            $participant->update([
                'wa_status' => 'Gagal: Data pelatihan tidak ditemukan',
            ]);
            return ['status' => false, 'message' => 'Data pelatihan tidak ditemukan'];
        }

        $message = self::buildInvitationMessage($training, $participant);

        // Get config from .env (supports wag_url / WAG_URL and wag_token / WAG_TOKEN)
        $rawUrl = env('wag_url') ?: env('WAG_URL') ?: 'waghub.mekayastudio.com';
        $token = env('wag_token') ?: env('WAG_TOKEN');

        if (!preg_match('/^https?:\/\//i', $rawUrl)) {
            $baseUrl = 'https://' . $rawUrl;
        } else {
            $baseUrl = $rawUrl;
        }
        $endpoint = rtrim($baseUrl, '/') . '/api/v1/messages';

        // If no token configured, simulate and log
        if (empty($token)) {
            Log::info("WAGHub Simulated to [{$phone}] ({$user->full_name}):\n" . $message);
            $participant->update([
                'wa_sent_at' => now(),
                'wa_status' => 'Terkirim (Simulasi / Log)',
            ]);
            return ['status' => true, 'message' => 'Simulasi sukses (token belum diisi)'];
        }

        $idempotencyKey = 'trn-' . $training->id . '-' . $participant->id . '-' . time() . '-' . Str::random(4);

        $payload = [
            'recipient' => [
                'type' => 'phone',
                'value' => $phone,
            ],
            'message' => [
                'type' => 'text',
                'text' => $message,
            ],
            'purpose' => 'otp',
            'mode' => 'sync',
            'route_key' => 'default',
            'idempotency_key' => $idempotencyKey,
            'expires_at' => now()->addDays(7)->toIso8601String(),
            'client_reference' => 'trn-' . $training->id . '-' . $participant->id,
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'Idempotency-Key' => $idempotencyKey,
                'Content-Type' => 'application/json',
            ])->withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])->connectTimeout(5)->timeout(15)->retry(2, 500)->post($endpoint, $payload);

            if ($response->successful()) {
                $participant->update([
                    'wa_sent_at' => now(),
                    'wa_status' => 'Terkirim',
                ]);
                return ['status' => true, 'message' => 'Pesan WA berhasil dikirim via WAGHub'];
            } else {
                $errJson = $response->json();
                $errorMsg = $errJson['message'] ?? ('HTTP Error ' . $response->status());
                
                // If 502 / upstream down, record informative status
                if ($response->status() == 502) {
                    $errorMsg = 'Gateway 502 (Server WAGHub sedang sibuk/offline)';
                }

                $participant->update([
                    'wa_status' => 'Gagal: ' . substr($errorMsg, 0, 100),
                ]);
                return ['status' => false, 'message' => 'Gagal WAGHub: ' . $errorMsg];
            }
        } catch (\Throwable $th) {
            Log::error('WAGHub send exception: ' . $th->getMessage());
            $errorMsg = $th->getMessage();
            if (str_contains($errorMsg, 'cURL error 28') || str_contains($errorMsg, 'timed out')) {
                $errorMsg = 'Koneksi ke gateway WhatsApp timeout (server gateway sedang lambat/tidak terjangkau). Coba kirim ulang beberapa saat lagi.';
            }
            $participant->update([
                'wa_status' => 'Error: ' . substr($errorMsg, 0, 100),
            ]);
            return ['status' => false, 'message' => $errorMsg];
        }
    }

    /**
     * Broadcast to all participants of a training
     */
    public static function broadcastTraining(Training $training): array
    {
        $participants = $training->participants()->with('user')->get();
        $success = 0;
        $failed = 0;

        foreach ($participants as $participant) {
            $result = self::sendToParticipant($participant);
            if ($result['status']) {
                $success++;
            } else {
                $failed++;
            }
        }

        return [
            'total' => $participants->count(),
            'success' => $success,
            'failed' => $failed,
        ];
    }
}
