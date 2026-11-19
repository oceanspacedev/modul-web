<?php

namespace App\Http\Requests;

use App\Models\OdActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tambah/ubah aktivitas Daily Report OD.
 */
class OdActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'name' => ['required', 'string', 'max:200'],
            'result' => ['required', Rule::in(OdActivity::RESULTS)],
            'od_document_id' => ['nullable', 'integer', Rule::exists('od_documents', 'id')->withoutTrashed()],
            'kendala' => ['nullable', 'string', 'max:300'],
            'solusi' => ['nullable', 'string', 'max:300'],
            'keterangan' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => 'tanggal',
            'name' => 'nama pekerjaan',
            'result' => 'hasil',
            'od_document_id' => 'dokumen terkait',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activityData(): array
    {
        return $this->safe()->only(['date', 'name', 'result', 'od_document_id', 'kendala', 'solusi', 'keterangan']);
    }
}
