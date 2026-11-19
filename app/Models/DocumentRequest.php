<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Pengajuan pembuatan dokumen oleh karyawan (lewat login atau link publik) untuk satu Kategori Dokumen. Setiap item
 * yang diajukan adalah satu OdDocument (relasi processes); status dan progres tiket dihitung dari tahap item-itemnya
 * terhadap tahapan kategori.
 */
class DocumentRequest extends Model
{
    use SoftDeletes;

    public const DEPARTMENTS = [
        'Human Capital Management',
        'Finance',
        'Supply Chain Management',
        'Management Information System',
        'Warehouse',
        'Internal Audit',
        'Online / Marcomm',
        'Retail',
        'Distribution Sales',
    ];

    public const JABATAN = ['Staff', 'Leader', 'Coordinator', 'Manager', 'Chief'];

    public const MAX_PROCESSES = 10;

    /** Huruf kode tiket tanpa karakter yang mudah tertukar (0/O, 1/I). */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const CODE_PATTERN = '/^[A-Z0-9]{2,6}-\d{8}-[A-Z0-9]{4}$/';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'obs_tanggal_1' => 'date',
            'obs_tanggal_2' => 'date',
            'jadwal_observasi' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<DocumentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /**
     * Item pengajuan beserta kategori dan tahapannya (dipakai untuk tahap, progres, dan tampilan pemohon).
     *
     * @return HasMany<OdDocument, $this>
     */
    public function processes(): HasMany
    {
        return $this->hasMany(OdDocument::class)->with('category.stages')->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<DocumentRequestHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(DocumentRequestHistory::class)->latest('created_at')->latest('id');
    }

    /**
     * Leader melihat semua pengajuan, Staff hanya pengajuan yang salah satu prosesnya PIC-nya dia.
     *
     * @param  Builder<DocumentRequest>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-od-report')) {
            $query->whereHas('processes', fn (Builder $q) => $q->where('pic_user_id', $user->getKey()));
        }
    }

    public static function generateCode(string $prefix = 'DOK'): string
    {
        do {
            $suffix = collect(range(1, 4))->map(fn () => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)])->implode('');
            $code = $prefix.'-'.now('Asia/Jakarta')->format('Ymd').'-'.$suffix;
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /** @return Collection<int, OdDocument> */
    private function activeProcesses(): Collection
    {
        return $this->processes->reject(fn (OdDocument $p) => $p->status?->isCancel())->values();
    }

    /** @return list<string> */
    public function stageNames(): array
    {
        return $this->category?->stageNames() ?? [];
    }

    /**
     * Status tiket = tahap proses yang paling tertinggal (proses yang dibatalkan tidak dihitung).
     */
    public function stage(): string
    {
        $stages = $this->stageNames();
        $active = $this->activeProcesses();
        if ($active->isEmpty()) {
            return $this->processes->isEmpty() ? ($stages[0] ?? '-') : OdDocument::STAGE_CANCELLED;
        }

        return $stages[$active->min(fn (OdDocument $p) => $p->stageIndex())] ?? '-';
    }

    /** Progres 0-100 = rata-rata tahap semua proses yang tidak dibatalkan. */
    public function progress(): int
    {
        $active = $this->activeProcesses();
        if ($active->isEmpty()) {
            return 0;
        }

        $last = max(1, count($this->stageNames()) - 1);

        return (int) round($active->avg(fn (OdDocument $p) => $p->stageIndex() / $last) * 100);
    }

    public function releasedCount(): int
    {
        return $this->processes->filter(fn (OdDocument $p) => $p->isReleased())->count();
    }

    /** Prioritas tertinggi di antara prosesnya. */
    public function topPriority(): ?string
    {
        $priorities = $this->processes->pluck('prioritas')->filter();

        foreach (OdDocument::PRIORITAS as $priority) {
            if ($priorities->contains($priority)) {
                return $priority;
            }
        }

        return null;
    }

    public function isFinished(): bool
    {
        return $this->processes->isNotEmpty() && $this->activeProcesses()->every(fn (OdDocument $p) => $p->isReleased());
    }

    /** Nomor WhatsApp untuk link wa.me (0812… → 62812…). */
    public function whatsappLink(): string
    {
        $number = preg_replace('/\D/', '', $this->whatsapp);
        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return 'https://wa.me/'.$number;
    }

    public function maskedWhatsapp(): string
    {
        $number = preg_replace('/\D/', '', $this->whatsapp);

        return strlen($number) > 6 ? substr($number, 0, 4).str_repeat('•', strlen($number) - 7).substr($number, -3) : '•••';
    }

    public function maskedEmail(): string
    {
        [$name, $domain] = array_pad(explode('@', $this->email, 2), 2, '');

        return Str::substr($name, 0, 2).'•••@'.$domain;
    }

    public static function priorityBadgeClass(?string $priority): string
    {
        return match ($priority) {
            'Urgent' => 'badge-danger',
            'High' => 'badge-warning',
            'Medium' => 'badge-info',
            'Low' => 'badge-light',
            default => 'badge-secondary',
        };
    }
}
