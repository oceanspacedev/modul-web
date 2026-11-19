<?php

namespace App\Models;

use App\Support\StorageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\MimeTypes;

/**
 * Dokumen yang ditangani tim OD (halaman Approval Dokumen). jenis = kode Kategori Dokumen. Dokumen dari Permintaan
 * Dokumen adalah satu "proses" pengajuan: punya document_request_id, tujuan, prioritas, sistem dan stage (tahapan
 * kategorinya) yang terlihat pemohon. Tahap terakhir kategori dan status bertipe done selalu berjalan bersama (lihat
 * applyStage() dan applyStatus()).
 */
class OdDocument extends Model
{
    use SoftDeletes;

    /** Saran isian badan usaha (bebas diketik). */
    public const BADAN_USAHA = ['CV CS', 'CV CS AUTO EV', 'CV TOP', 'PT SMI', 'PT MSI', 'PT CSN', 'RISM'];

    public const PRIORITAS = ['Urgent', 'High', 'Medium', 'Low'];

    public const STAGE_CANCELLED = 'Dibatalkan';

    /** File dokumen rilis: PDF atau Office, maks 50 MB. */
    public const FILE_RULES = ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:51200'];

    public const FILE_REQUIRED_MESSAGE = 'Unggah file dokumen sebelum rilis.';

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

    /** @return BelongsTo<DocumentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'jenis', 'code');
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

    /** @return list<string> */
    public function stageNames(): array
    {
        return $this->category?->stageNames() ?? [];
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    /** Dokumen dari permintaan harus punya file sebelum dirilis (file dikirim ke pemohon). */
    public function needsFileToRelease(): bool
    {
        return $this->isFromRequest() && ! $this->hasFile();
    }

    /**
     * Link file untuk pemohon (WhatsApp dan halaman lacak). Bertanda tangan dan tanpa kedaluwarsa, jadi hanya link
     * yang dibuat aplikasi yang bisa dibuka.
     */
    public function fileLink(): ?string
    {
        return $this->hasFile() ? URL::signedRoute('document-request.file', ['document' => $this->getKey()]) : null;
    }

    /**
     * File sebagai lampiran WhatsApp. URL harus bisa diunduh gateway: URL sementara 1 hari untuk S3, selain itu link
     * bertanda tangan aplikasi.
     *
     * @return array{url: string, filename: string, mime_type: string, caption: string}|null
     */
    public function whatsAppAttachment(): ?array
    {
        if (! $this->hasFile()) {
            return null;
        }

        $extension = strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));

        return [
            'url' => Storage::disk()->providesTemporaryUrls() ? StorageUrl::for($this->file_path, 24 * 60) : $this->fileLink(),
            'filename' => $this->file_name ?: basename($this->file_path),
            'mime_type' => MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream',
            'caption' => "File dokumen: {$this->title}",
        ];
    }

    /** Hasil simpan berupa rilis: tahap terakhir kategori atau status selesai. */
    public function wouldRelease(?string $stage, ?OdDocumentStatus $status): bool
    {
        $final = $this->category?->finalStage();

        return ($stage !== null && $final !== null && $stage === $final) || (bool) $status?->isDone();
    }

    public function stageIndex(): int
    {
        $index = array_search($this->stage, $this->stageNames(), true);

        return $index === false ? 0 : $index;
    }

    /** Sudah di tahap terakhir kategorinya (rilis). */
    public function isReleased(): bool
    {
        return $this->stage !== null && $this->stage === $this->category?->finalStage();
    }

    /** Tahap yang dilihat pemohon: dokumen yang statusnya batal tampil sebagai "Dibatalkan". */
    public function publicStage(): string
    {
        return $this->status?->isCancel() ? self::STAGE_CANCELLED : ($this->stage ?? $this->category?->firstStage() ?? '-');
    }

    /**
     * Ubah tahap. Tahap terakhir = status selesai; keluar dari tahap terakhir mengembalikan status ke berjalan.
     */
    public function applyStage(string $stage): void
    {
        $this->stage = $stage;

        if ($this->isReleased()) {
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
     * Ubah status di Approval Dokumen. Untuk dokumen dari pengajuan, status selesai = tahap terakhir dan sebaliknya.
     */
    public function applyStatus(OdDocumentStatus $status): void
    {
        $this->status()->associate($status);
        $stages = $this->stageNames();

        if ($status->isDone()) {
            $this->received_at ??= today();
            if ($this->isFromRequest() && $stages) {
                $this->stage = end($stages);
            }
        } else {
            $this->received_at = null;
            if ($this->isFromRequest() && $this->isReleased()) {
                $this->stage = $stages[count($stages) - 2] ?? $stages[0];
            }
        }
    }
}
