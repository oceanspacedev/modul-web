<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soal pilihan ganda (A–E); bank soal yang sama dipakai untuk pretest dan posttest.
 */
class LearningModuleQuestion extends Model
{
    public const OPTION_LETTERS = ['a', 'b', 'c', 'd', 'e'];

    protected $guarded = ['id'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    /**
     * Opsi yang terisi, mis. ['a' => 'Cuci tangan', 'b' => 'Makan'].
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];
        foreach (self::OPTION_LETTERS as $letter) {
            if (filled($this->{'option_'.$letter})) {
                $options[$letter] = $this->{'option_'.$letter};
            }
        }

        return $options;
    }
}
