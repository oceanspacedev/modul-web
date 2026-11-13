<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu percobaan mengirim undangan WhatsApp ke peserta: otomatis lewat WAGHub (berhasil atau gagal), atau manual
 * (pemateri membuka WhatsApp dengan teks undangan; terkirim tidaknya tidak bisa dipastikan sistem).
 */
class TrainingWaLog extends Model
{
    public const CHANNEL_AUTO = 'otomatis';

    public const CHANNEL_MANUAL = 'manual';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TrainingParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TrainingParticipant::class, 'training_participant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by')->withTrashed();
    }

    public function isManual(): bool
    {
        return $this->channel === self::CHANNEL_MANUAL;
    }

    /**
     * Hasil percobaan untuk tabel riwayat.
     */
    public function resultLabel(): string
    {
        return match (true) {
            $this->isManual() => 'Dibuka manual',
            $this->success => 'Berhasil',
            default => 'Gagal',
        };
    }
}
