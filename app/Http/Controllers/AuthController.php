<?php

namespace App\Http\Controllers;

use App\Models\LoginOtp;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Resolve a user by username or WhatsApp number variations
     */
    public function findUserByIdentifier(string $loginInput): ?User
    {
        $loginInput = trim($loginInput);
        if ($loginInput === '') {
            return null;
        }

        // Extract digits if the input contains a phone number
        $digitsOnly = preg_replace('/[^0-9]/', '', $loginInput);

        // Build phone variations (e.g. 08..., 628..., +628...)
        $phoneCandidates = [];
        if (! empty($digitsOnly) && strlen($digitsOnly) >= 7) {
            $phoneCandidates[] = $loginInput;
            $phoneCandidates[] = $digitsOnly;

            if (str_starts_with($digitsOnly, '62')) {
                $phoneCandidates[] = '0'.substr($digitsOnly, 2);
            } elseif (str_starts_with($digitsOnly, '0')) {
                $phoneCandidates[] = '62'.substr($digitsOnly, 1);
                $phoneCandidates[] = '+62'.substr($digitsOnly, 1);
            } elseif (str_starts_with($digitsOnly, '8')) {
                $phoneCandidates[] = '0'.$digitsOnly;
                $phoneCandidates[] = '62'.$digitsOnly;
                $phoneCandidates[] = '+62'.$digitsOnly;
            }
        }

        return User::where(function ($query) use ($loginInput, $digitsOnly, $phoneCandidates) {
            $query->where('username', $loginInput);

            if (! empty($phoneCandidates)) {
                $query->orWhereIn('no_wa', array_unique($phoneCandidates));

                if (strlen($digitsOnly) >= 7) {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(no_wa, '-', ''), ' ', ''), '+', '') = ?", [$digitsOnly]);
                    if (str_starts_with($digitsOnly, '62')) {
                        $query->orWhereRaw("REPLACE(REPLACE(REPLACE(no_wa, '-', ''), ' ', ''), '+', '') = ?", ['0'.substr($digitsOnly, 2)]);
                    } elseif (str_starts_with($digitsOnly, '0')) {
                        $query->orWhereRaw("REPLACE(REPLACE(REPLACE(no_wa, '-', ''), ' ', ''), '+', '') = ?", ['62'.substr($digitsOnly, 1)]);
                    }
                }
            }
        })->first();
    }

    /**
     * Standard Login with Password
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = trim($request->input('username'));
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        $user = $this->findUserByIdentifier($loginInput);

        if ($user && Hash::check($password, $user->password)) {
            Auth::login($user, $remember);
            $request->session()->regenerate();

            return redirect()->intended('dashboard');
        }

        return back()->withInput($request->only('username'))->with([
            'error' => 'Username / Nomor WhatsApp atau Password salah.',
        ]);
    }

    /**
     * Send WhatsApp OTP for passwordless login
     */
    public function requestOtp(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
        ]);

        $user = $this->findUserByIdentifier($request->input('username'));

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Akun dengan username atau nomor WhatsApp tersebut tidak ditemukan.',
            ], 404);
        }

        if (empty($user->no_wa)) {
            return response()->json([
                'status' => false,
                'message' => 'Akun ini belum memiliki nomor WhatsApp terdaftar. Silakan masuk menggunakan password.',
            ], 422);
        }

        $formattedPhone = WhatsAppService::formatPhoneNumber($user->no_wa);
        if (! $formattedPhone) {
            return response()->json([
                'status' => false,
                'message' => 'Format nomor WhatsApp pengguna tidak valid.',
            ], 422);
        }

        // Check cooldown (60 seconds between OTP requests)
        $recentOtp = LoginOtp::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subSeconds(60))
            ->latest()
            ->first();

        if ($recentOtp) {
            $secondsRemaining = max(1, 60 - (int) now()->diffInSeconds($recentOtp->created_at, true));

            return response()->json([
                'status' => false,
                'message' => "Mohon tunggu {$secondsRemaining} detik sebelum meminta kode OTP kembali.",
                'cooldown' => $secondsRemaining,
            ], 429);
        }

        // Generate 6-digit numeric OTP code
        $otp = (string) random_int(100000, 999999);

        // Invalidate previous unused OTPs
        LoginOtp::where('user_id', $user->id)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        // Store new OTP valid for 5 minutes
        LoginOtp::create([
            'user_id' => $user->id,
            'phone' => $formattedPhone,
            'otp_code' => $otp,
            'expires_at' => now()->addMinutes(5),
            'is_used' => false,
        ]);

        // Send OTP via WhatsApp
        $sendResult = WhatsAppService::sendOtp(
            $formattedPhone,
            $otp,
            $user->full_name ?: $user->username
        );

        if (! $sendResult['status']) {
            return response()->json([
                'status' => false,
                'message' => $sendResult['message'] ?? 'Gagal mengirim kode OTP ke WhatsApp.',
            ], 500);
        }

        $maskedPhone = $this->maskPhoneNumber($formattedPhone);

        return response()->json([
            'status' => true,
            'message' => "Kode OTP 6-digit telah dikirim ke WhatsApp {$maskedPhone}.",
            'phone_masked' => $maskedPhone,
            'cooldown' => 60,
            'expires_in' => 300,
        ]);
    }

    /**
     * Verify WhatsApp OTP and authenticate user
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'otp' => ['required', 'string'],
        ]);

        $user = $this->findUserByIdentifier($request->input('username'));

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Akun tidak ditemukan.',
            ], 404);
        }

        $otpInput = trim($request->input('otp'));
        $otpRecord = LoginOtp::where('user_id', $user->id)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otpRecord || $otpRecord->otp_code !== $otpInput) {
            return response()->json([
                'status' => false,
                'message' => 'Kode OTP salah atau telah kadaluarsa. Silakan periksa kembali atau minta kode baru.',
            ], 422);
        }

        // Mark OTP as used
        $otpRecord->update(['is_used' => true]);

        // Log the user in
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json([
            'status' => true,
            'message' => 'Login berhasil! Mengalihkan ke halaman dashboard...',
            'redirect' => url('/dashboard'),
        ]);
    }

    /**
     * Helper to mask phone number for privacy
     */
    private function maskPhoneNumber(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 7) {
            return $phone;
        }

        return substr($phone, 0, 4).'****'.substr($phone, -4);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
