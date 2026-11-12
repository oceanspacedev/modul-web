<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu pertanyaan form evaluasi milik sebuah pelatihan.
 */
class TrainingEvaluationQuestion extends Model
{
    /** Skala 1-5 dengan label di kedua ujungnya. */
    public const TYPE_SCALE = 'scale';

    /** Jawaban teks bebas. */
    public const TYPE_TEXT = 'text';

    /** Satu pilihan dari daftar opsi. */
    public const TYPE_CHOICE = 'choice';

    public const TYPES = [
        self::TYPE_SCALE => 'Skala 1-5',
        self::TYPE_TEXT => 'Jawaban teks',
        self::TYPE_CHOICE => 'Pilihan',
    ];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function isScale(): bool
    {
        return $this->type === self::TYPE_SCALE;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }
}
