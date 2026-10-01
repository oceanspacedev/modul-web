<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    public function fetch()
    {
        try {
            $user = User::with('divisi', 'subdivisi', 'joblevel')->find(Auth::id());
            return ResponseFormatter::success($user, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    public function login(Request $request)
    {
        /**
         * @param Request $request
         * @return \Illuminate\Http\JsonResponse
         * @throws \Exception
         */
        try {
            $request->validate([
                'username' => 'required',
                'password' => 'required',
            ]);

            $credentials = request(['username', 'password']);
            if (!Auth::attempt($credentials)) {
                return ResponseFormatter::error([
                    'message' => 'Unauthorized'
                ], 'Gagal login, cek kembali username dan password anda', 500);
            }

            $user = User::where('username', $request->username)->first();
            if (!Hash::check($request->password, $user->password, [])) {
                throw new Exception('Invalid Credentials');
            }
            // $user->id_notif = $request->id_notif;
            // $user->update();

            $tokenResult = $user->createToken('authToken')->plainTextToken;
            return ResponseFormatter::success([
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => $user
            ], 'Selamat datang kembali ' . auth()->user()->full_name);
        } catch (Exception $error) {
            return ResponseFormatter::error(null, $error->getMessage(), 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return ResponseFormatter::success(null, 'Sampai jumpa kembali ' . auth()->user()->full_name);
        } catch (Exception $e) {
            return ResponseFormatter::success(null, $e->getMessage());

        }
    }
}
