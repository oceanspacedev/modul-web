<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pekerjaan di Daily Report tim OD. saved_at kosong = masih draft (belum "Simpan Laporan"); Weekly dan Monthly
 * hanya membaca aktivitas yang sudah disimpan.
 */
class OdActivity extends Model
{
    public const RESULT_DONE = 'Done';

    public const RESULT_PROGRESS = 'On Progress';

    public const RESULT_HOLD = 'Hold';

    public const RESULTS = [self::RESULT_DONE, self::RESULT_PROGRESS, self::RESULT_HOLD];

    /** Status di template Weekly Report tim. */
    public const WEEKLY_STATUS = [
        self::RESULT_DONE => 'Closed',
        self::RESULT_PROGRESS => 'On Progress',
        self::RESULT_HOLD => 'Open',
    ];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'saved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<OdDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(OdDocument::class, 'od_document_id');
    }

    /** @param Builder<OdActivity> $query */
    public function scopeReported(Builder $query): void
    {
        $query->whereNotNull('saved_at');
    }

    /**
     * Leader melihat aktivitas semua anggota tim, Staff hanya miliknya.
     *
     * @param  Builder<OdActivity>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-od-report')) {
            $query->where('user_id', $user->getKey());
        }
    }

    public function canBeEditedBy(User $user): bool
    {
        return $user->can('manage-od-report') || $this->user_id === $user->getKey();
    }

    public function isDraft(): bool
    {
        return $this->saved_at === null;
    }

    public function badgeClass(): string
    {
        return match ($this->result) {
            self::RESULT_DONE => 'badge-success',
            self::RESULT_HOLD => 'badge-secondary',
            default => 'badge-warning',
        };
    }
}
