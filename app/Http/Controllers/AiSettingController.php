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
        return view('ai-settings.index', [
            'title' => 'Pengaturan AI',
            'active' => 'ai-settings',
            'activeProvider' => AiSettings::provider()->key(),
            'configured' => AiSettings::isConfigured(),
            'providers' => collect(AiProviders::all())->map(fn (AiProvider $provider) => [
                'provider' => $provider,
                'maskedKey' => self::mask(AiSettings::storedApiKey($provider)),
                'source' => AiSettings::source($provider),
                'model' => AiSettings::storedModel($provider),
            ]),
        ]);
    }

    /**
     * Simpan pengaturan semua provider, lalu cek API key provider aktif dengan memuat daftar modelnya.
     * API key kosong = key lama dipertahankan; centang "Hapus key" untuk menghapusnya.
     */
    public function update(Request $request): RedirectResponse
    {
        $providers = AiProviders::all();
        $secretFields = array_map(fn (string $key) => "{$key}_api_key", array_keys($providers));

        $rules = ['provider' => ['required', Rule::in(array_keys($providers))]];
        $attributes = ['provider' => 'provider aktif'];
        foreach ($providers as $key => $provider) {
            $rules["{$key}_api_key"] = ['nullable', 'string', 'max:500'];
            $rules["{$key}_model"] = ['nullable', 'string', 'max:120', 'regex:'.self::MODEL_PATTERN];
            $rules["{$key}_forget"] = ['nullable', 'boolean'];
            $attributes["{$key}_api_key"] = "API key {$provider->label()}";
            $attributes["{$key}_model"] = "model {$provider->label()}";
        }

        $validator = Validator::make($request->all(), $rules, [], $attributes);
        $validator->after(function ($validator) use ($request) {
            $provider = AiProviders::find($request->input('provider'));
            if (! $provider) {
                return;
            }

            $key = $provider->key();
            $hasKey = filled($request->input("{$key}_api_key"))
                || (! $request->boolean("{$key}_forget") && filled(AiSettings::storedApiKey($provider)))
                || filled($provider->envApiKey());
            if (! $hasKey) {
                $validator->errors()->add("{$key}_api_key", "Isi API key {$provider->label()} untuk provider aktif.");
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
        $values = [AiSetting::PROVIDER => $data['provider']];
        foreach (array_keys($providers) as $key) {
            if ($request->boolean("{$key}_forget")) {
                $values[AiSetting::apiKeyKey($key)] = null;
            } elseif (filled($data["{$key}_api_key"] ?? null)) {
                $values[AiSetting::apiKeyKey($key)] = $data["{$key}_api_key"];
            }
            $values[AiSetting::modelKey($key)] = $data["{$key}_model"] ?? null;
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

    private static function mask(?string $key): ?string
    {
        return filled($key) ? '••••'.substr($key, -4) : null;
    }
}
