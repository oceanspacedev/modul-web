<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TrainingMeetingMonitor extends Model
{
    public const IDLE_SECONDS = 45;

    /** Petugas memantau dari browser selama rapat berjalan. */
    public const SOURCE_LIVE = 'live';

    /** Data ditarik dari API platform meeting. */
    public const SOURCE_IMPORT = 'import';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(TrainingMeetingEvent::class, 'monitor_id');
    }

    public function isActiveAt(Carbon $time): bool
    {
        return $this->source !== self::SOURCE_IMPORT
            && $this->ended_at === null
            && $this->last_seen_at->greaterThan($time->copy()->subSeconds(self::IDLE_SECONDS));
    }
}
