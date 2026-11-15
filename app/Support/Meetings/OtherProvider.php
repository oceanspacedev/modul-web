<?php

namespace App\Support\Meetings;

/** Platform di luar daftar: tautannya tetap dipakai, tanpa integrasi. */
class OtherProvider extends MeetingProvider
{
    public function key(): string
    {
        return 'other';
    }

    public function label(): string
    {
        return 'Lainnya';
    }

    public function matchesHost(string $host): bool
    {
        return false;
    }
}
