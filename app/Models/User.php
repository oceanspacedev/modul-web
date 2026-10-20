<?php

namespace App\Models;

use App\Services\DataAccessService;
use App\Support\WhatsAppNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['employee_id', 'nama_lengkap', 'username', 'email', 'no_hp', 'password', 'data_access', 'area_id', 'divisi_id', 'position_id', 'approval_id'])]
#[Hidden(['password', 'remember_token', 'created_at', 'updated_at'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'ADMIN';

    public const ROLE_STAFF = 'STAFF';

    /** Admin melihat seluruh badan usaha. */
    public const DATA_ACCESS_ALL = 'all';

    /** Admin hanya melihat badan usaha di relasi dataAccessAreas. */
    public const DATA_ACCESS_SELECTED = 'selected';

    /** Hak akses tambahan trainer, diberikan langsung ke user di luar jabatannya. */
    public const TRAINER_PERMISSIONS = ['manage-trainings', 'manage-videos', 'manage-documents'];

    protected static function booted(): void
    {
        // Ganti No. HP = ganti nomor login WhatsApp, jadi OTP lama yang belum dipakai tidak berlaku lagi.
        static::updated(function (User $user): void {
            if ($user->wasChanged('no_hp')) {
                LoginOtp::where('user_id', $user->getKey())->where('is_used', false)->update(['expires_at' => now()]);
            }
        });
    }

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

    /** @return Attribute<never, mixed> */
    protected function noHp(): Attribute
    {
        return Attribute::make(
            set: function (mixed $value): ?string {
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    return null;
                }

                if (is_bool($value) || (! is_scalar($value) && ! $value instanceof \Stringable)) {
                    throw new InvalidArgumentException('No. HP tidak valid.');
                }

                $number = WhatsAppNumber::toLocal((string) $value);

                if (! $number) {
                    throw new InvalidArgumentException('No. HP harus berupa nomor WhatsApp Indonesia yang valid.');
                }

                return $number;
            },
        );
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * Super admin (ADMIN dengan akses seluruh badan usaha) melewati setiap pengecekan permission.
     */
    public function isAdministrator(): bool
    {
        return DataAccessService::hasGlobalAccess($this);
    }

    /**
     * Jabatan karyawan (satu role Spatie per user, seperti role_id di DnD).
     *
     * @return Attribute<Role|null, never>
     */
    protected function jabatan(): Attribute
    {
        return Attribute::get(fn (): ?Role => $this->roles->first());
    }

    public function isTrainer(): bool
    {
        return $this->getDirectPermissions()->whereIn('name', self::TRAINER_PERMISSIONS)->isNotEmpty();
    }

    public function setTrainer(bool $trainer): void
    {
        $trainer
            ? $this->givePermissionTo(self::TRAINER_PERMISSIONS)
            : $this->revokePermissionTo(self::TRAINER_PERMISSIONS);
    }

    /**
     * Pasang satu jabatan. Karyawan yang baru dipromosikan menjadi ADMIN tanpa pilihan akses data dibatasi ke
     * badan usahanya sendiri (fail closed), tidak diam-diam menjadi super admin.
     */
    public function assignJabatan(Role $role, bool $dataAccessChosen = false): void
    {
        $wasAdmin = $this->exists && $this->isAdmin();

        $this->syncRoles([$role]);
        $this->unsetRelation('roles');

        if ($role->name === self::ROLE_ADMIN && ! $wasAdmin && ! $dataAccessChosen) {
            $this->forceFill(['data_access' => self::DATA_ACCESS_SELECTED])->save();
            $this->grantOwnAreaAccess();
        }

        DataAccessService::clearMemo();
    }

    /**
     * Akses data badan usaha milik karyawan ini (users.area_id) bila belum ada badan usaha yang dipilih.
     */
    public function grantOwnAreaAccess(): void
    {
        if (filled($this->area_id) && ! $this->dataAccessAreas()->exists()) {
            $this->dataAccessAreas()->attach((int) $this->area_id);
        }

        $this->unsetRelation('dataAccessAreas');
        DataAccessService::clearMemo();
    }

    public function scopeFilter($query)
    {
        if (request('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('employee_id', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('no_hp', 'like', '%'.$search.'%');
            });
        }
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function divisi()
    {
        return $this->belongsTo(Divisi::class);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Atasan / approval line.
     *
     * @return BelongsTo<User, $this>
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_id');
    }

    /** @return HasMany<User, $this> */
    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'approval_id');
    }

    /**
     * Badan usaha yang boleh diakses admin dengan akses data 'selected'.
     *
     * @return BelongsToMany<Area, $this>
     */
    public function dataAccessAreas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'user_area_access')->withTimestamps();
    }
}
