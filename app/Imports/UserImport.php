<?php

namespace App\Imports;

use App\Models\Area;
use App\Models\Divisi;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalScopeService;
use App\Services\DataAccessService;
use App\Support\WhatsAppNumber;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Import Excel karyawan dengan kolom yang sama seperti DnD: user dicocokkan lewat id_karyawan lalu username,
 * area/divisi/posisi dibuat bila belum ada, jabatan (role) harus sudah ada.
 *
 * Baris yang bermakna ganda ditolak dengan nomor barisnya, bukan ditebak: ID karyawan dan username yang menunjuk dua
 * orang berbeda, nama yang sama dengan user lain tanpa username, user yang muncul dua kali di file, dan approval yang
 * tidak ditemukan atau cocok dengan lebih dari satu orang. Baris lain tetap diimpor.
 */
class UserImport implements ToModel, WithHeadingRow
{
    public const DEFAULT_PASSWORD = 'complete123';

    /** @var list<string> */
    private array $errors = [];

    private int $rowNumber = 1;

    private int $successCount = 0;

    /** @var array<int, int> id user => baris yang sudah mengimpornya di file ini */
    private array $importedRows = [];

    /** @var list<array{userId: int, input: string, row: int}> approval yang belum ditemukan saat barisnya dibaca */
    private array $pendingApprovals = [];

    public function model(array $row): null
    {
        $this->rowNumber++;

        try {
            DB::transaction(fn () => $this->importRow($row));
            $this->successCount++;
        } catch (Throwable $e) {
            $this->errors[] = "Baris {$this->rowNumber}: {$e->getMessage()}";
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function successCount(): int
    {
        return $this->successCount;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importRow(array $row): void
    {
        $namaLengkap = Str::upper(Str::squish((string) $this->value($row, ['nama_lengkap', 'nama', 'name'])));
        $employeeId = trim((string) $this->value($row, ['id_karyawan', 'employee_id']));
        $rawUsername = (string) $this->value($row, ['username']);
        $username = $this->normalizeUsername($rawUsername !== '' ? $rawUsername : $namaLengkap);
        $hasPhone = $this->hasColumn($row, ['no_hp', 'hp', 'phone', 'no_telepon', 'telepon']);
        $hasEmail = $this->hasColumn($row, ['email', 'email_address']);
        $phone = $this->phone($this->value($row, ['no_hp', 'hp', 'phone', 'no_telepon', 'telepon']));
        $email = $this->email($this->value($row, ['email', 'email_address']));

        $user = $employeeId !== '' ? User::withTrashed()->where('employee_id', $employeeId)->first() : null;
        $matchedByDerivedUsername = false;
        if ($user) {
            $this->rejectUsernameConflict($user, $rawUsername, $username);
        } elseif ($username !== '') {
            $user = User::withTrashed()->where('username', $username)->first();
            $matchedByDerivedUsername = $user && $rawUsername === '';

            // Username cocok tetapi ID karyawannya berbeda: dua orang berbeda (mis. nama sama), bukan user yang sama.
            if ($user && $employeeId !== '' && filled($user->employee_id) && $user->employee_id !== $employeeId) {
                throw new Exception($matchedByDerivedUsername
                    ? "Nama ini menghasilkan username \"{$username}\" yang sudah dipakai user lain (ID karyawan {$user->employee_id}). Isi kolom username dengan username lain untuk user baru ini"
                    : "Username \"{$username}\" sudah dipakai user dengan ID karyawan {$user->employee_id}, berbeda dengan ID di baris ini ({$employeeId})");
            }
        }

        if ($user && isset($this->importedRows[$user->id])) {
            throw new Exception("User ini sudah diimpor di baris {$this->importedRows[$user->id]} file yang sama. Sisakan satu baris per user");
        }

        if ($user && $hasPhone && $matchedByDerivedUsername) {
            throw new Exception('No. HP login user existing hanya boleh diubah melalui ID karyawan atau username eksplisit yang cocok');
        }

        $roleInput = Str::squish((string) $this->value($row, ['role', 'role_name', 'jabatan']));
        $role = $roleInput === '' ? null : Role::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($roleInput)])
            ->orWhereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [Str::lower(str_replace(' ', '', $roleInput))])
            ->first();
        if (! $role) {
            throw new Exception('Jabatan (role) "'.($roleInput ?: '-').'" tidak ditemukan');
        }

        $area = $this->master(Area::class, (string) $this->value($row, ['area', 'area_name']));
        if (! $area) {
            throw new Exception('Area wajib diisi');
        }

        $divisi = $this->master(Divisi::class, (string) $this->value($row, ['divisi', 'divisi_name']), ['area_id' => $area->id]);
        if (! $divisi) {
            throw new Exception('Divisi wajib diisi');
        }

        $position = $this->master(Position::class, (string) $this->value($row, ['position', 'posisi', 'position_name']));

        // "-" adalah isi kolom approval kosong pada file export lama.
        $approvalInput = Str::squish((string) $this->value($row, ['approval', 'approval_nama_lengkap']));
        $approvalInput = $approvalInput === '-' ? '' : $approvalInput;
        $approval = $approvalInput === '' ? null : $this->findApproval($approvalInput);

        if ($approval && $user && ($approval->is($user) || ApprovalScopeService::createsApprovalCycle($user->id, $approval->id))) {
            throw new Exception("Approval \"{$approvalInput}\" membentuk siklus: ia adalah user ini sendiri atau bawahannya");
        }

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }

            if ($role->name !== User::ROLE_ADMIN && DataAccessService::hasGlobalAccess($user) && ! DataAccessService::anotherGlobalAdminExists($user)) {
                throw new Exception('Minimal harus ada satu super admin; jabatan ADMIN tidak dilepas');
            }

            $data = [
                'area_id' => $area->id,
                'divisi_id' => $divisi->id,
                'position_id' => $position?->id ?? $user->position_id,
                'approval_id' => $approval?->id ?? $user->approval_id,
            ];

            if ($employeeId !== '') {
                $data['employee_id'] = $employeeId;
            }
            if ($namaLengkap !== '') {
                $data['nama_lengkap'] = $namaLengkap;
            }
            if ($hasPhone) {
                $data['no_hp'] = $phone;
            }
            if ($hasEmail) {
                $data['email'] = $email;
            }

            $user->update($data);
        } else {
            if ($namaLengkap === '') {
                throw new Exception('Nama lengkap tidak boleh kosong');
            }

            if ($username === '') {
                throw new Exception('Username tidak dapat dibentuk');
            }

            $password = (string) $this->value($row, ['password']);

            $user = User::create([
                'employee_id' => $employeeId !== '' ? $employeeId : null,
                'nama_lengkap' => $namaLengkap,
                'username' => $username,
                'no_hp' => $phone,
                'email' => $email,
                'area_id' => $area->id,
                'divisi_id' => $divisi->id,
                'position_id' => $position?->id,
                'approval_id' => $approval?->id,
                'password' => $password !== '' ? $password : self::DEFAULT_PASSWORD,
            ]);
        }

        // ADMIN dari import tidak pernah otomatis menjadi super admin (dibatasi ke badan usahanya sendiri).
        if ($user->jabatan?->isNot($role)) {
            $user->assignJabatan($role);
        }

        $this->importedRows[$user->id] = $this->rowNumber;

        // Atasan bisa saja baru dibuat oleh baris di bawahnya; dicari lagi setelah semua baris selesai.
        if ($approvalInput !== '' && ! $approval) {
            $this->pendingApprovals[] = ['userId' => $user->id, 'input' => $approvalInput, 'row' => $this->rowNumber];
        }
    }

    /**
     * Pasang approval yang belum ditemukan saat barisnya dibaca (atasannya ada di baris sesudahnya). Dipanggil setelah
     * seluruh file selesai. Yang tetap tidak ditemukan dilaporkan; data lain user itu sudah tersimpan.
     */
    public function resolvePendingApprovals(): void
    {
        foreach ($this->pendingApprovals as $pending) {
            try {
                $user = User::findOrFail($pending['userId']);
                $approval = $this->findApproval($pending['input'])
                    ?? throw new Exception("Approval \"{$pending['input']}\" tidak ditemukan sebagai nama lengkap atau ID karyawan");

                if ($approval->is($user) || ApprovalScopeService::createsApprovalCycle($user->id, $approval->id)) {
                    throw new Exception("Approval \"{$pending['input']}\" membentuk siklus: ia adalah user ini sendiri atau bawahannya");
                }

                $user->update(['approval_id' => $approval->id]);
            } catch (Throwable $e) {
                $this->errors[] = "Baris {$pending['row']}: {$e->getMessage()}. Data lain user ini tersimpan, approval-nya tidak diubah.";
            }
        }

        $this->pendingApprovals = [];
    }

    /**
     * Atasan dari ID karyawan atau nama lengkapnya; null bila belum ada. Nama yang dimiliki lebih dari satu user
     * ditolak karena tidak bisa dipastikan siapa yang dimaksud.
     */
    private function findApproval(string $input): ?User
    {
        $byEmployeeId = User::query()->where('employee_id', $input)->first();
        if ($byEmployeeId) {
            return $byEmployeeId;
        }

        $byName = User::query()->whereRaw('LOWER(nama_lengkap) = ?', [Str::lower($input)])->limit(2)->get();
        if ($byName->count() > 1) {
            throw new Exception("Approval \"{$input}\" cocok dengan lebih dari satu user. Isi kolom approval dengan ID karyawan atasannya");
        }

        return $byName->first();
    }

    /**
     * User ditemukan lewat ID karyawan, tetapi kolom username menunjuk ke tempat lain: milik user lain, atau berbeda
     * dari username user ini (username tidak diubah lewat import).
     */
    private function rejectUsernameConflict(User $user, string $rawUsername, string $username): void
    {
        if ($rawUsername === '' || $username === $this->normalizeUsername((string) $user->username)) {
            return;
        }

        $owner = User::withTrashed()->where('username', $username)->whereKeyNot($user->id)->first();

        throw new Exception($owner
            ? "ID karyawan menunjuk {$user->nama_lengkap}, tetapi username \"{$username}\" milik {$owner->nama_lengkap}. Samakan keduanya"
            : "Username di file (\"{$username}\") berbeda dengan username user ini (\"{$user->username}\"). Username tidak diubah lewat import; kosongkan kolomnya atau samakan");
    }

    /**
     * @template TModel of Area|Divisi|Position
     *
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $extra
     * @return TModel|null
     */
    private function master(string $model, string $name, array $extra = []): mixed
    {
        $name = Str::squish($name);

        if ($name === '') {
            return null;
        }

        return $model::query()->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first()
            ?? $model::create(['name' => $name, ...$extra]);
    }

    private function normalizeUsername(string $value): string
    {
        return Str::lower(str_replace('.', '', preg_replace('/\s+/', '', $value) ?? $value));
    }

    private function phone(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = is_float($value) ? sprintf('%.0f', $value) : (string) $value;

        return WhatsAppNumber::toLocal($value) ?? throw new Exception('No. HP harus berupa nomor WhatsApp Indonesia yang valid');
    }

    private function email(mixed $value): ?string
    {
        $email = Str::lower(trim((string) $value));

        if ($email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : throw new Exception('Format email tidak valid');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function value(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function hasColumn(array $row, array $keys): bool
    {
        return array_intersect($keys, array_keys($row)) !== [];
    }
}
