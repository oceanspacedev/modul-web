<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use App\Models\OdDocument;
use App\Support\OdTeam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ubah Kategori Dokumen beserta tahapannya. Tahapan dikirim berurutan; baris dengan id = tahap lama (boleh diganti
 * nama), tanpa id = tahap baru, tahap lama yang tidak dikirim = dihapus (ditolak bila masih dipakai dokumen).
 */
class DocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'needs_observation' => $this->boolean('needs_observation'),
            'is_active' => $this->boolean('is_active'),
            'remove_example' => $this->boolean('remove_example'),
            'remove_template' => $this->boolean('remove_template'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->category();

        return [
            ...self::identityRules($category),
            'description' => ['nullable', 'string', 'max:300'],
            'needs_observation' => ['boolean'],
            'is_active' => ['boolean'],
            'pic_user_id' => ['nullable', 'integer', Rule::in(OdTeam::query()->pluck('id')->all())],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'example' => ['nullable', ...DocumentCategory::EXAMPLE_RULES],
            'remove_example' => ['boolean'],
            'template' => ['nullable', ...DocumentCategory::TEMPLATE_RULES],
            'remove_template' => ['boolean'],
            'stages' => ['required', 'array', 'min:'.DocumentCategory::MIN_STAGES, 'max:'.DocumentCategory::MAX_STAGES],
            'stages.*.id' => ['nullable', 'integer', Rule::exists('document_category_stages', 'id')->where('document_category_id', $category->getKey())],
            'stages.*.name' => ['required', 'string', 'max:40', 'distinct:ignore_case', Rule::notIn([OdDocument::STAGE_CANCELLED])],
        ];
    }

    /**
     * Aturan kode dan nama, dipakai juga saat menambah kategori.
     *
     * @return array<string, mixed>
     */
    public static function identityRules(?DocumentCategory $category = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[\pL\pN][\pL\pN .\/&-]*$/u', Rule::unique('document_categories', 'code')->ignore($category)],
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $category = $this->category();
            $inUse = $category->isInUse();

            if ($inUse && $this->input('code') !== $category->code) {
                $validator->errors()->add('code', 'Kode tidak bisa diubah karena kategori ini sudah dipakai dokumen.');
            }

            $kept = collect($this->input('stages', []))->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $removed = $category->stages->reject(fn ($stage) => $kept->contains($stage->id));
            foreach ($removed as $stage) {
                if (OdDocument::withTrashed()->where('jenis', $category->code)->whereNull('document_request_id')->where('stage', $stage->name)->exists()) {
                    $validator->errors()->add('stages', "Tahap \"{$stage->name}\" masih dipakai dokumen, jadi tidak bisa dihapus.");
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama',
            'description' => 'keterangan',
            'pic_user_id' => 'PIC bawaan',
            'sort' => 'urutan',
            'example' => 'file contoh',
            'template' => 'file template',
            'stages' => 'tahapan',
            'stages.*.name' => 'judul tahap',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh huruf, angka, spasi, titik, garis miring, & dan tanda hubung.',
            'stages.min' => 'Minimal :min tahap.',
            'stages.*.name.distinct' => 'Judul tahap tidak boleh sama.',
            'stages.*.name.not_in' => 'Judul tahap "Dibatalkan" dipakai sistem untuk dokumen yang batal.',
        ];
    }

    private function category(): DocumentCategory
    {
        return $this->route('category');
    }
}
