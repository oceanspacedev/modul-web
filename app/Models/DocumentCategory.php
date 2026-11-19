<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori Dokumen (IK, JD, SE, MEMO, ...): data master jenis dokumen OD beserta tahapan pengerjaannya. Kode kategori
 * disimpan di od_documents.jenis, jadi kode dikunci begitu dipakai. Tahap terakhir berarti dokumen selesai (rilis).
 */
class DocumentCategory extends Model
{
    /** Kategori cadangan untuk jenis yang tidak dikenal. */
    public const FALLBACK_CODE = 'Dokumen Lainnya';

    /** Tahapan awal kategori baru; bisa diubah setelah dibuat. */
    public const DEFAULT_STAGES = [
        'Diajukan' => 'Pengajuan diterima dan menunggu ditinjau oleh tim Organization Development (OD).',
        'Drafting' => 'Tim OD sedang menyusun draf dokumen.',
        'Proses review PIC' => 'Draf sedang direview oleh PIC. Mohon bantu merespons bila diminta.',
        'Proses review OD Leader' => 'Draf sedang direview oleh OD Leader.',
        'Proses Esign' => 'Dokumen sedang dalam proses tanda tangan elektronik.',
        'Release' => 'Dokumen sudah dirilis dan dapat dicek serta diunduh di cloud dokumen.',
    ];

    public const MIN_STAGES = 2;

    public const MAX_STAGES = 20;

    /** File contoh yang boleh diunggah: dokumen Office, PDF, atau gambar, maks. 20 MB. */
    public const EXAMPLE_RULES = ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png', 'max:20480'];

    /** Contoh bawaan aplikasi, dipakai selama kategorinya belum punya file contoh sendiri. */
    private const BUNDLED_EXAMPLES = ['IK' => 'assets/permintaan-dokumen/contoh-ik.pdf'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'needs_observation' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<DocumentCategoryStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(DocumentCategoryStage::class)->orderBy('sort')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    /** @return HasMany<DocumentRequest, $this> */
    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    /** @param Builder<DocumentCategory> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /** @param Builder<DocumentCategory> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public static function forCode(?string $code): ?self
    {
        return static::where('code', $code)->first() ?? static::where('code', self::FALLBACK_CODE)->first();
    }

    /** @return list<string> */
    public function stageNames(): array
    {
        return $this->stages->pluck('name')->all();
    }

    public function firstStage(): ?string
    {
        return $this->stages->first()?->name;
    }

    public function finalStage(): ?string
    {
        return $this->stages->last()?->name;
    }

    public function stageDescription(?string $stage): ?string
    {
        return $this->stages->firstWhere('name', $stage)?->description;
    }

    /**
     * PIC bawaan, selama orangnya masih punya akses OD Report.
     */
    public function picUser(): ?User
    {
        $user = $this->pic;

        return $user && $user->can('access-od-report') ? $user : null;
    }

    /** Awalan kode tiket: kode kategori bila pendek dan alfanumerik (IK, JD, MEMO), selain itu DOK. */
    public function ticketPrefix(): string
    {
        return preg_match('/^[A-Z0-9]{2,6}$/', $this->code) ? $this->code : 'DOK';
    }

    public function documentsCount(): int
    {
        return OdDocument::withTrashed()->where('jenis', $this->code)->count();
    }

    public function isInUse(): bool
    {
        return OdDocument::withTrashed()->where('jenis', $this->code)->exists()
            || $this->documentRequests()->withTrashed()->exists();
    }

    public function hasUploadedExample(): bool
    {
        return filled($this->example_path);
    }

    public function hasExample(): bool
    {
        return $this->hasUploadedExample() || isset(self::BUNDLED_EXAMPLES[$this->code]);
    }

    public function exampleUrl(): ?string
    {
        return match (true) {
            $this->hasUploadedExample() => route('document-request.example-file', $this),
            isset(self::BUNDLED_EXAMPLES[$this->code]) => asset(self::BUNDLED_EXAMPLES[$this->code]),
            default => null,
        };
    }

    /** Nama file contoh untuk tampilan dan unduhan. */
    public function exampleFileName(): ?string
    {
        return $this->hasUploadedExample()
            ? $this->example_name
            : (isset(self::BUNDLED_EXAMPLES[$this->code]) ? basename(self::BUNDLED_EXAMPLES[$this->code]) : null);
    }

    /** Cara menampilkan contoh di halaman: pdf, image, atau file (hanya unduh). */
    public function exampleViewer(): string
    {
        $path = $this->example_path ?? self::BUNDLED_EXAMPLES[$this->code] ?? '';

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'pdf',
            'jpg', 'jpeg', 'png' => 'image',
            default => 'file',
        };
    }

    public function label(): string
    {
        return $this->code === $this->name ? $this->name : "{$this->code} · {$this->name}";
    }
}
