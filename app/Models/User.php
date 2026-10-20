<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['id_karyawan', 'full_name', 'username', 'email', 'no_wa', 'password', 'divisi_id', 'sub_divisi_id', 'job_level_id'])]
#[Hidden(['password', 'remember_token', 'created_at', 'updated_at'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Administrators (Admin role or the top job level) bypass every permission check.
     */
    public function isAdministrator(): bool
    {
        return $this->job_level_id == 1 || $this->hasRole('Admin');
    }

    public function scopeFilter($query)
    {
        if (request('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('id_karyawan', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('no_wa', 'like', '%'.$search.'%');
            });
        }
    }

    public function divisi()
    {
        return $this->belongsTo(Divisi::class);
    }

    public function subdivisi()
    {
        return $this->belongsTo(SubDivisi::class, 'sub_divisi_id');
    }

    public function joblevel()
    {
        return $this->belongsTo(JobLevel::class, 'job_level_id');
    }
}
