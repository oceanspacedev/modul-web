<?php

namespace App\Models;

use App\Models\Concerns\HasQuizImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Soal kuis pelatihan: pilihan ganda (A–F) atau essay. Pertanyaan dan tiap pilihan boleh bergambar. Tiap soal bisa
 * dipakai di pretest saja, posttest saja, atau keduanya (used_in).
 */
class TrainingQuestion extends Model
{
    use HasFactory;
    use HasQuizImages;

    public const OPTION_LETTERS = ['a', 'b', 'c', 'd', 'e', 'f'];

    public const USED_IN_BOTH = 'both';

    /** Di fase mana soal dipakai bila pelatihan punya pretest dan posttest. */
    public const USED_IN = [
        self::USED_IN_BOTH => 'Pretest & posttest',
        Training::PHASE_PRETEST => 'Pretest saja',
        Training::PHASE_POSTTEST => 'Posttest saja',
    ];

    protected $guarded = ['id'];

    /**
     * Soal baru tanpa pilihan fase (impor, AI): pilihan ganda dipakai di kedua fase, essay hanya di posttest karena
     * harus dinilai pemateri. Pemateri bisa mengubahnya per soal.
     */
    protected static function booted(): void
    {
        static::creating(function (TrainingQuestion $question): void {
            $question->used_in ??= $question->type === 'essay' ? Training::PHASE_POSTTEST : self::USED_IN_BOTH;
        });
    }

    /**
     * Kunci jawaban bisa dipakai menilai: soal pilihan ganda harus menunjuk salah satu pilihannya yang terisi. Essay
     * dinilai pemateri, jadi selalu dianggap valid.
     */
    public function hasValidKey(): bool
    {
        return $this->type === 'essay'
            || array_key_exists(strtolower((string) $this->correct_answer), $this->options());
    }

    public function isUsedIn(string $phase): bool
    {
        return in_array($this->used_in ?? self::USED_IN_BOTH, [self::USED_IN_BOTH, $phase], true);
    }

    public function usedInLabel(): string
    {
        return self::USED_IN[$this->used_in ?? self::USED_IN_BOTH] ?? self::USED_IN[self::USED_IN_BOTH];
    }

    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }
}
