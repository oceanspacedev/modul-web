<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komentar tim OD untuk pemohon Permintaan Dokumen. Tampil di halaman lacak dan dikirim ke WhatsApp pemohon.
 */
class DocumentRequestComment extends Model
{
    public const MAX_LENGTH = 1000;

    protected $guarded = ['id'];

    /** @return BelongsTo<DocumentRequest, $this> */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
