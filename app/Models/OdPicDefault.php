<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PIC bawaan per jenis dokumen OD: dokumen baru (termasuk proses dari Permintaan Dokumen) otomatis diberikan ke dia.
 */
class OdPicDefault extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * PIC untuk jenis ini, selama orangnya masih punya akses OD Report.
     */
    public static function userFor(string $jenis): ?User
    {
        $jenis = in_array($jenis, OdDocument::JENIS, true) ? $jenis : 'Dokumen Lainnya';
        $user = static::where('jenis', $jenis)->first()?->user;

        return $user && $user->can('access-od-report') ? $user : null;
    }
}
