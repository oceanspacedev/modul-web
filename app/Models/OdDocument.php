<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dokumen yang ditangani tim OD (halaman Approval Dokumen). Dokumen dari Permintaan Dokumen adalah satu "proses"
 * pengajuan: punya document_request_id, tujuan, prioritas, sistem dan stage (8 tahap IK) yang terlihat pemohon.
 * Stage Release dan status bertipe done selalu berjalan bersama (lihat applyStage() dan applyStatus()).
 */
class OdDocument extends Model
{
    use SoftDeletes;

    public const JENIS = ['IK', 'JD', 'SOP', 'SE', 'MEMO', 'SA', 'Dokumen Lainnya'];

    /** Urutan dokumen di ekspor ACHIEVEMENT bulanan. */
    public const JENIS_EXPORT_ORDER = ['SOP', 'SE', 'MEMO', 'SA', 'IK', 'JD'];

    /** Saran isian badan usaha (bebas diketik). */
    public const BADAN_USAHA = ['CV CS', 'CV CS AUTO EV', 'CV TOP', 'PT SMI', 'PT MSI', 'PT CSN', 'RISM'];

    public const PRIORITAS = ['Urgent', 'High', 'Medium', 'Low'];

    public const STAGE_RELEASE = 'Release';

    /** Tahap pembuatan IK dari pengajuan, berurutan, beserta artinya untuk pemohon. */
    public const STAGES = [
        'Diajukan' => 'Pengajuan diterima dan menunggu ditinjau oleh tim Organization Development (OD).',
        'Dijadwalkan observasi' => 'Tim OD sudah menetapkan jadwal observasi. Mohon siapkan proses kerja sesuai jadwal.',
        'Dalam proses observasi' => 'Tim OD sedang mengamati langsung proses kerja sebagai bahan penyusunan IK.',
        'Drafting' => 'Tim OD sedang menyusun draf dokumen Instruksi Kerja.',
        'Proses review PIC' => 'Draf IK sedang direview oleh PIC proses. Mohon bantu merespons bila diminta.',
        'Proses review OD Leader' => 'Draf IK sedang direview oleh OD Leader.',
        'Proses Esign' => 'Dokumen IK sedang dalam proses tanda tangan elektronik.',
        'Release' => 'Dokumen IK sudah dirilis dan dapat dicek serta diunduh di cloud dokumen.',
    ];

    public const STAGE_CANCELLED = 'Dibatalkan';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'date',
            'received_at' => 'date',
        ];
    }

    /** @return BelongsTo<OdDocumentStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(OdDocumentStatus::class, 'od_document_status_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id')->withTrashed();
    }

    /** @return BelongsTo<DocumentRequest, $this> */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    /** @return HasMany<OdActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(OdActivity::class);
    }

    /**
     * Leader melihat semua dokumen, Staff hanya dokumen yang PIC-nya dia.
     *
     * @param  Builder<OdDocument>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-od-report')) {
            $query->where('pic_user_id', $user->getKey());
        }
    }

    public function canBeEditedBy(User $user): bool
    {
        return $user->can('manage-od-report') || $this->pic_user_id === $user->getKey();
    }

    public function isFromRequest(): bool
    {
        return $this->document_request_id !== null;
    }

    public function stageIndex(): int
    {
        $index = array_search($this->stage, array_keys(self::STAGES), true);

        return $index === false ? 0 : $index;
    }

    /** Tahap yang dilihat pemohon: dokumen yang statusnya batal tampil sebagai "Dibatalkan". */
    public function publicStage(): string
    {
        return $this->status?->isCancel() ? self::STAGE_CANCELLED : ($this->stage ?? array_key_first(self::STAGES));
    }

    /**
     * Ubah tahap IK. Release = status selesai; keluar dari Release mengembalikan status ke berjalan.
     */
    public function applyStage(string $stage): void
    {
        $this->stage = $stage;

        if ($stage === self::STAGE_RELEASE) {
            if (! $this->status?->isDone() && ($done = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE))) {
                $this->status()->associate($done);
            }
            $this->received_at ??= today();
        } elseif ($this->status?->isDone() && ($open = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN))) {
            $this->status()->associate($open);
            $this->received_at = null;
        }
    }

    /**
     * Ubah status di Approval Dokumen. Untuk dokumen dari pengajuan, status selesai = stage Release dan sebaliknya.
     */
    public function applyStatus(OdDocumentStatus $status): void
    {
        $this->status()->associate($status);

        if ($status->isDone()) {
            $this->received_at ??= today();
            if ($this->isFromRequest()) {
                $this->stage = self::STAGE_RELEASE;
            }
        } else {
            $this->received_at = null;
            if ($this->isFromRequest() && $this->stage === self::STAGE_RELEASE) {
                $this->stage = 'Proses Esign';
            }
        }
    }
}
