<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingMeetingEvent extends Model
{
    public const TYPES = ['present', 'join', 'leave', 'camera'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'camera_on' => 'boolean',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(TrainingMeetingMonitor::class, 'monitor_id');
    }
}
