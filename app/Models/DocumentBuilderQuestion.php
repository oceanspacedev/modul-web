<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu langkah form Dokumen Builder milik sebuah Kategori Dokumen (diatur OD Leader).
 */
class DocumentBuilderQuestion extends Model
{
    public const TYPES = ['input' => 'Singkat', 'textarea' => 'Panjang'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }

    /** @return BelongsTo<DocumentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /** Bentuk yang dipakai template builder. */
    public function toQuestion(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'hint' => (string) $this->hint,
            'placeholder' => (string) $this->placeholder,
            'type' => $this->type,
            'required' => $this->required,
        ];
    }
}
