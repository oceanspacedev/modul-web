<?php

namespace App\Services\Helpdesk;

use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Services\DocumentRequestService;
use App\Support\DocumentRequestRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * POST tickets dari helpdesk (kontrak integrasi §3.3): isian answers[...] dan identitas reporter[...] dipetakan ke data
 * form Permintaan Dokumen, divalidasi dengan aturan yang sama seperti form web (DocumentRequestRules), lalu dibuat lewat
 * DocumentRequestService. Kunci error dikembalikan relatif terhadap kiriman helpdesk (answers.proses.0.judul,
 * reporter.name, ...).
 */
class TicketIntake
{
    /** Kunci data form yang tidak berasal dari answers[...], dan nama field-nya di kiriman helpdesk. */
    private const ERROR_KEYS = [
        'kategori' => 'category_key',
        'nama' => 'reporter.name',
        'whatsapp' => 'reporter.phone',
        'idempotency_key' => 'idempotency_key',
    ];

    /** Field form yang diambil dari answers[...]. */
    private const ANSWER_KEYS = ['email', 'divisi', 'departemen', 'jabatan', 'atasan', 'badan_usaha', 'proses', 'obs_tanggal_1', 'obs_tanggal_2', 'obs_catatan'];

    public function __construct(private DocumentRequestService $service) {}

    /**
     * Pengajuan yang sudah dibuat dengan idempotency_key yang sama (kiriman ulang helpdesk).
     */
    public function existing(Request $request): ?DocumentRequest
    {
        $key = $request->input('idempotency_key');
        if (! is_string($key) || $key === '') {
            return null;
        }

        // Termasuk pengajuan yang sudah dihapus: kuncinya tetap terpakai (indeks unik), dan kiriman ulang mendapat
        // snapshot dibatalkan, bukan error.
        $existing = DocumentRequest::withTrashed()->where('idempotency_key', $key)->first();
        if ($existing && ! $existing->isFromHelpdesk()) {
            throw ValidationException::withMessages(['idempotency_key' => 'Kunci idempotensi sudah dipakai pengajuan lain.']);
        }

        return $existing;
    }

    /**
     * @throws ValidationException
     */
    public function create(Request $request): DocumentRequest
    {
        $request->validate([
            'idempotency_key' => ['required', 'string', 'max:'.DocumentRequestRules::IDEMPOTENCY_KEY_MAX],
            'helpdesk_ticket_number' => ['nullable', 'string', 'max:64'],
            'category_key' => ['required', 'string'],
            'reporter' => ['nullable', 'array'],
            'answers' => ['nullable', 'array'],
        ]);

        $category = DocumentCategory::active()->where('code', $request->input('category_key'))->first();
        if (! $category) {
            throw ValidationException::withMessages(['category_key' => 'Kategori tidak ditemukan atau tidak aktif.']);
        }

        $data = DocumentRequestRules::normalize($this->formData($request, $category), $category);

        $validator = Validator::make($data, DocumentRequestRules::rules($category), DocumentRequestRules::messages(), DocumentRequestRules::attributes());
        $validator->after(fn ($validator) => DocumentRequestRules::checkLampiranTotal($validator, (array) ($data['proses'] ?? [])));

        if ($validator->fails()) {
            throw ValidationException::withMessages($this->remapErrors($validator->errors()->messages()));
        }

        $nik = trim((string) $request->input('reporter.nik'));
        $user = $nik !== '' ? User::where('employee_id', $nik)->first() : null;

        return $this->service->create([
            ...$validator->validated(),
            'source' => DocumentRequest::SOURCE_HELPDESK,
            'helpdesk_ticket_number' => $request->input('helpdesk_ticket_number'),
        ], $user);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, DocumentCategory $category): array
    {
        $answers = $request->input('answers');
        $files = $request->file('answers');
        $answers = array_replace_recursive(is_array($answers) ? $answers : [], is_array($files) ? $files : []);

        return [
            ...collect($answers)->only(self::ANSWER_KEYS)->all(),
            'kategori' => $category->getKey(),
            'nama' => $request->input('reporter.name'),
            'whatsapp' => $request->input('reporter.phone'),
            'idempotency_key' => $request->input('idempotency_key'),
        ];
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @return array<string, list<string>>
     */
    private function remapErrors(array $errors): array
    {
        $mapped = [];
        foreach ($errors as $key => $messages) {
            $mapped[self::ERROR_KEYS[$key] ?? "answers.{$key}"] = $messages;
        }

        return $mapped;
    }
}
