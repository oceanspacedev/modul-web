<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Support\Ai\AiProvider;
use App\Support\Ai\AiProviders;
use App\Support\Ai\AiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Pengaturan AI (admin): provider aktif, API key dan model tiap provider. Menggantikan GEMINI_API_KEY / GEMINI_MODEL
 * di .env, yang tetap dipakai sebagai cadangan selama pengaturan ini kosong.
 */
class AiSettingController extends Controller
{
    private const MODEL_PATTERN = '/^[A-Za-z0-9._:\/-]+$/';

    public function index(): View
    {
        $active = AiSettings::provider();

        return view('ai-settings.index', [
            'title' => 'Pengaturan AI',
            'active' => 'ai-settings',
            'activeProvider' => $active->key(),
            'activeLabel' => $active->label(),
            'activeModel' => AiSettings::model($active),
            'configured' => AiSettings::isConfigured(),
            'providers' => collect(AiProviders::all())->map(fn (AiProvider $provider) => [
                'provider' => $provider,
                'maskedKey' => self::mask(AiSettings::storedApiKey($provider)),
                'source' => AiSettings::source($provider),
                'model' => AiSettings::model($provider),
            ]),
        ]);
    }

    /**
     * Simpan provider yang dipilih beserta API key dan modelnya, lalu cek key-nya dengan memuat daftar model.
     * Pengaturan provider lain tidak berubah. API key kosong = key lama dipertahankan; "Hapus key" menghapusnya.
     */
    public function update(Request $request): RedirectResponse
    {
        $providers = AiProviders::all();
        $secretFields = array_map(fn (string $key) => "{$key}_api_key", array_keys($providers));
        $provider = AiProviders::find($request->input('provider'));

        $rules = ['provider' => ['required', Rule::in(array_keys($providers))]];
        $attributes = ['provider' => 'provider'];
        if ($provider) {
            $key = $provider->key();
            $rules["{$key}_api_key"] = ['nullable', 'string', 'max:500'];
            $rules["{$key}_model"] = ['nullable', 'string', 'max:120', 'regex:'.self::MODEL_PATTERN];
            $rules["{$key}_forget"] = ['nullable', 'boolean'];
            $attributes["{$key}_api_key"] = "API key {$provider->label()}";
            $attributes["{$key}_model"] = "model {$provider->label()}";
        }

        $validator = Validator::make($request->all(), $rules, [], $attributes);
        $validator->after(function ($validator) use ($request, $provider) {
            if (! $provider) {
                return;
            }

            $key = $provider->key();
            $hasKey = filled($request->input("{$key}_api_key"))
                || (! $request->boolean("{$key}_forget") && filled(AiSettings::storedApiKey($provider)))
                || filled($provider->envApiKey());
            if (! $hasKey) {
                $validator->errors()->add("{$key}_api_key", "Isi API key {$provider->label()}.");
            }
            if (blank($request->input("{$key}_model")) && blank($provider->defaultModel())) {
                $validator->errors()->add("{$key}_model", "Pilih model {$provider->label()}.");
            }
        });

        // API key tidak ikut dikembalikan ke form saat validasi gagal.
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except($secretFields));
        }

        $data = $validator->validated();
        $key = $provider->key();
        $values = [
            AiSetting::PROVIDER => $key,
            AiSetting::modelKey($key) => $data["{$key}_model"] ?? null,
        ];
        if ($request->boolean("{$key}_forget")) {
            $values[AiSetting::apiKeyKey($key)] = null;
        } elseif (filled($data["{$key}_api_key"] ?? null)) {
            $values[AiSetting::apiKeyKey($key)] = $data["{$key}_api_key"];
        }
        AiSetting::put($values);

        $provider = AiSettings::provider();
        try {
            $models = $provider->listModels((string) AiSettings::apiKey($provider));
        } catch (Throwable $e) {
            return back()->with('error', "Tersimpan, tapi {$provider->label()} belum tersambung: {$e->getMessage()}");
        }

        $model = AiSettings::model($provider);
        if ($models !== [] && ! array_key_exists($model, $models)) {
            return back()->with('error', "Tersimpan, tapi model \"{$model}\" tidak ada di daftar model akun {$provider->label()} ini.");
        }

        return back()->with('success', "Tersimpan. AI memakai {$provider->label()} ({$model}).");
    }

    /** Daftar model sebuah provider untuk tombol "Muat model", memakai key yang diketik atau yang tersimpan. */
    public function models(Request $request, string $provider): JsonResponse
    {
        $provider = AiProviders::find($provider) ?? abort(404);
        $apiKey = trim((string) $request->input('api_key')) ?: AiSettings::apiKey($provider);

        if (blank($apiKey)) {
            return response()->json(['message' => "Isi API key {$provider->label()} dulu."], 422);
        }

        try {
            $models = $provider->listModels($apiKey);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'models' => collect($models)->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])->values(),
        ]);
    }

    /**
     * "Coba chat": kirim percakapan singkat ke provider dengan key dan model di form (belum perlu disimpan), supaya
     * admin melihat sendiri AI-nya benar-benar menjawab. Hanya teks pendek, jadi tidak lewat queue.
     */
    public function chat(Request $request, string $provider): JsonResponse
    {
        $provider = AiProviders::find($provider) ?? abort(404);
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['required', 'string', 'max:120', 'regex:'.self::MODEL_PATTERN],
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.text' => ['required', 'string', 'max:8000'],
        ], [], ['message' => 'pesan', 'model' => 'model']);

        $apiKey = trim((string) ($data['api_key'] ?? '')) ?: AiSettings::apiKey($provider);
        if (blank($apiKey)) {
            return response()->json(['message' => "Isi API key {$provider->label()} dulu."], 422);
        }

        $messages = [
            ...array_map(fn (array $message) => ['role' => $message['role'], 'text' => $message['text']], $data['history'] ?? []),
            ['role' => 'user', 'text' => $data['message']],
        ];

        $started = microtime(true);
        try {
            $reply = trim($provider->chat($messages, $apiKey, $data['model']));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($reply === '') {
            return response()->json(['message' => "{$provider->label()} tidak mengembalikan jawaban."], 422);
        }

        return response()->json(['reply' => $reply, 'ms' => (int) round((microtime(true) - $started) * 1000)]);
    }

    private static function mask(?string $key): ?string
    {
        return filled($key) ? '••••'.substr($key, -4) : null;
    }
}
