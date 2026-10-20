<?php

namespace App\Enums;

enum TrainingStatus: string
{
    case Scheduled = 'scheduled';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Dijadwalkan',
            self::Ongoing => 'Berlangsung',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Bootstrap badge classes used by the admin panel.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Scheduled => 'badge-warning text-white',
            self::Ongoing => 'badge-success',
            self::Completed => 'badge-secondary',
            self::Cancelled => 'badge-danger',
        };
    }
}
