<?php

namespace App\Models;

use App\Support\DocumentBuilderContent;
use App\Support\DocumentBuilderTemplates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dokumen Builder: draf dokumen yang disusun tim OD lewat form bertahap (dibantu AI) lalu diedit per bagian.
 * Bentuk isinya mengikuti template kategorinya (DocumentBuilderTemplates).
 */
class DocumentDraft extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['ai_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'content' => 'array',
            'effective_date' => 'date',
        ];
    }

    /** @return BelongsTo<DocumentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Item Permintaan Dokumen asal draf ini (bila dibuat dari permintaan).
     *
     * @return BelongsTo<OdDocument, $this>
     */
    public function odDocument(): BelongsTo
    {
        return $this->belongsTo(OdDocument::class);
    }

    /**
     * Isi awal form builder dari satu item Permintaan Dokumen. Data hanya diisikan ke langkah yang ada (langkah bisa
     * sudah diubah OD Leader); sisanya tetap sampai ke AI lewat requestContext().
     *
     * @param  array{key: string, questions: list<array{key: string}>}  $template
     * @return array<string, string>
     */
    public static function prefillFrom(OdDocument $process, array $template): array
    {
        $purposeKey = match ($template['key']) {
            'JD' => 'purpose',
            'SE' => 'background',
            'MEMO' => 'message',
            'IK' => 'activity',
            default => 'objective',
        };
        $toolsKey = $template['key'] === 'IK' ? 'equipment' : 'tools';

        $values = [
            'title' => $process->title,
            'department' => $process->documentRequest?->departemen,
            $purposeKey => $process->tujuan,
            $toolsKey => $process->sistem,
        ];

        $keys = array_column($template['questions'], 'key');

        return collect($values)
            ->only($keys)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->all();
    }

    /**
     * Data permintaan yang ikut dikirim ke AI sebagai konteks (butuh relasi odDocument.documentRequest).
     *
     * @return array<string, string>
     */
    public function requestContext(): array
    {
        $process = $this->odDocument;
        $request = $process?->documentRequest;

        if (! $request) {
            return [];
        }

        return array_filter([
            'Kode permintaan' => $request->code,
            'Pemohon' => trim($request->nama.' ('.$request->jabatan.')'),
            'Divisi / departemen' => collect([$request->divisi, $request->departemen])->filter()->implode(' / '),
            'Atasan pemohon' => $request->atasan,
            'Judul yang diminta' => $process->title,
            'Tujuan menurut pemohon' => $process->tujuan,
            'Sistem / alat yang dipakai' => $process->sistem,
            'Prioritas' => $process->prioritas,
            'Catatan tim OD' => $request->catatan_admin,
        ], fn ($value) => filled($value));
    }

    /**
     * Leader melihat semua draf, Staff hanya draf buatannya sendiri.
     *
     * @param  Builder<DocumentDraft>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-od-report')) {
            $query->where('user_id', $user->getKey());
        }
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->can('manage-od-report') || $this->user_id === $user->getKey();
    }

    /** Template kategori draf ini (butuh relasi category). */
    public function template(): array
    {
        return DocumentBuilderTemplates::for($this->category);
    }

    /**
     * Isi dokumen yang selalu lengkap sesuai bagian template, termasuk bagian yang masih kosong.
     *
     * @return array<string, mixed>
     */
    public function sections(): array
    {
        return DocumentBuilderContent::normalize($this->template()['sections'], $this->content ?? []);
    }

    public function hasContent(): bool
    {
        return ! DocumentBuilderContent::isEmpty($this->sections());
    }

    /** Nama file unduhan tanpa ekstensi, mis. "SOP - Inspeksi HSE Lapangan". */
    public function fileBaseName(): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $this->category->code.' - '.$this->title);

        return trim(mb_substr(preg_replace('/\s+/', ' ', $name), 0, 120));
    }
}
