<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Satu materi modul: Video Materi atau Dokumen yang sudah ada.
 */
class LearningModuleItem extends Model
{
    public const TYPE_VIDEO = 'video';

    public const TYPE_DOCUMENT = 'document';

    protected $guarded = ['id'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    public function materialable(): MorphTo
    {
        return $this->morphTo();
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LearningModuleItemProgress::class);
    }

    public function isVideo(): bool
    {
        return $this->materialable_type === self::TYPE_VIDEO;
    }

    public function title(): string
    {
        return $this->isVideo() ? $this->materialable?->title ?? '-' : $this->materialable?->name ?? '-';
    }

    /**
     * Kunci pilihan di form modul, mis. "video:5".
     */
    public function key(): string
    {
        return $this->materialable_type.':'.$this->materialable_id;
    }
}
