<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningModuleItemProgress extends Model
{
    protected $table = 'learning_module_item_progress';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LearningModuleItem::class, 'learning_module_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
