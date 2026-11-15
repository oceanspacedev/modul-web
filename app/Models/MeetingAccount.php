<?php

namespace App\Models;

use App\Support\Meetings\MeetingProvider;
use App\Support\Meetings\MeetingProviders;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Akun penyelenggara di satu platform meeting. Kredensialnya mengikuti MeetingProvider::credentialFields().
 */
#[Fillable(['user_id', 'provider', 'name', 'credentials'])]
#[Hidden(['credentials'])]
class MeetingAccount extends Model
{
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
        ];
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class, 'meeting_account_id');
    }

    public function meetingProvider(): ?MeetingProvider
    {
        return MeetingProviders::find($this->provider);
    }

    public function credential(string $field): ?string
    {
        return $this->credentials[$field] ?? null;
    }
}
