<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status dokumen di Approval Dokumen OD. Nama bisa diatur Leader; tipe menentukan arti status:
 * open = masih berjalan, done = selesai/rilis, cancel = dibatalkan.
 */
class OdDocumentStatus extends Model
{
    public const TYPE_OPEN = 'open';

    public const TYPE_DONE = 'done';

    public const TYPE_CANCEL = 'cancel';

    public const TYPES = [
        self::TYPE_OPEN => 'Berjalan',
        self::TYPE_DONE => 'Selesai',
        self::TYPE_CANCEL => 'Batal',
    ];

    protected $guarded = ['id'];

    /** @return HasMany<OdDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(OdDocument::class);
    }

    /** @param Builder<OdDocumentStatus> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    public static function firstOfType(string $type): ?self
    {
        return static::where('type', $type)->ordered()->first();
    }

    public function isOpen(): bool
    {
        return $this->type === self::TYPE_OPEN;
    }

    public function isDone(): bool
    {
        return $this->type === self::TYPE_DONE;
    }

    public function isCancel(): bool
    {
        return $this->type === self::TYPE_CANCEL;
    }

    public function badgeClass(): string
    {
        return match ($this->type) {
            self::TYPE_DONE => 'badge-success',
            self::TYPE_CANCEL => 'badge-secondary',
            default => 'badge-warning',
        };
    }
}
