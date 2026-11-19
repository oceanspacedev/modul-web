<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nomor WhatsApp pengelola yang menerima kabar setiap ada Permintaan Dokumen baru. Nomor disimpan dalam format 62….
 */
class DocumentRequestRecipient extends Model
{
    public const MAX = 10;

    protected $guarded = ['id'];
}
