<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

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

    public function lastSeen()
    {
        return $this->hasMany(PersonalAccessToken::class, 'tokenable_id');
    }

    public function absent()
    {
        return $this->hasMany(Absent::class, 'user_id');
    }
}
