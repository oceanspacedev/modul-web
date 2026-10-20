<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Divisi;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Support\WhatsAppNumber;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sinkronisasi karyawan dari DnD (REST API /api/v1) ke modul ini. DnD adalah sumber data:
 * master area/divisi/posisi/jabatan dibuat bila belum ada, user dicocokkan lewat Employee ID lalu username.
 *
 * - User baru mendapat password acak (API DnD tidak mengirim password) dan masuk lewat OTP WhatsApp.
 * - User lokal yang tidak ada di DnD dan user yang diarsipkan tidak diubah.
 * - Jabatan ADMIN dari DnD dibatasi ke badan usahanya sendiri, tidak pernah otomatis menjadi super admin.
 */
class DndUserSyncService
{
    private const PER_PAGE = 100;

    public static function isConfigured(): bool
    {
        return filled(config('services.dnd.url'))
            && (filled(config('services.dnd.token')) || (filled(config('services.dnd.login')) && filled(config('services.dnd.password'))));
    }

    /**
     * Ambil seluruh data yang dibutuhkan dari DnD.
     *
     * @return array{areas: array<int, string>, divisis: array<int, string>, positions: array<int, string>, roles: array<int, string>, users: array<int, array<string, mixed>>}
     */
    public function fetch(): array
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('Koneksi DnD belum diatur. Isi DND_API_URL dan DND_API_TOKEN (atau DND_API_LOGIN & DND_API_PASSWORD) di file .env.');
        }

        $client = $this->client();

        $users = [];
        $page = 1;
        do {
            $response = $this->get($client, 'users', ['per_page' => self::PER_PAGE, 'page' => $page]);
            array_push($users, ...$response['data']);
            $lastPage = (int) ($response['meta']['last_page'] ?? 1);
            $page++;
        } while ($page <= $lastPage);

        return [
            'areas' => $this->names($this->get($client, 'master/areas')['data']),
            'divisis' => $this->names($this->get($client, 'master/divisis')['data']),
            'positions' => $this->names($this->get($client, 'master/positions')['data']),
            'roles' => $this->names($this->get($client, 'master/roles')['data']),
            'users' => array_map($this->normalizeUser(...), $users),
        ];
    }

    /**
     * Rencana perubahan tanpa menulis apa pun, untuk halaman preview.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{masters: array<string, array<int, string>>, create: array<int, array<string, mixed>>, update: array<int, array<string, mixed>>, unchanged: int, skipped: array<int, string>, local_only: int}
     */
    public function plan(array $snapshot): array
    {
        $masters = [
            'Area' => $this->missingNames(Area::query()->pluck('name')->all(), $snapshot['areas']),
            'Divisi' => $this->missingNames(Divisi::query()->pluck('name')->all(), $snapshot['divisis']),
            'Posisi' => $this->missingNames(Position::query()->pluck('name')->all(), $snapshot['positions']),
            'Jabatan' => $this->missingNames(Role::query()->pluck('name')->all(), $snapshot['roles']),
        ];

        $lookup = $this->localLookup();
        $create = $update = $skipped = [];
        $unchanged = 0;
        $matchedIds = [];

        foreach ($snapshot['users'] as $row) {
            $label = "{$row['nama_lengkap']} ({$row['employee_id']})";
            $user = $this->matchUser($row, $lookup);

            if ($user === false) {
                $skipped[] = "{$label}: Employee ID dan username menunjuk user berbeda.";

                continue;
            }

            if ($user?->trashed()) {
                $skipped[] = "{$label}: user diarsipkan di modul ini, tidak diubah.";
                $matchedIds[] = $user->id;

                continue;
            }

            if ($user === null) {
                $create[] = $row;

                continue;
            }

            $matchedIds[] = $user->id;
            $changes = $this->changes($user, $row);

            if ($changes === []) {
                $unchanged++;
            } else {
                $update[] = ['label' => $label, 'changes' => $changes];
            }
        }

        return [
            'masters' => array_filter($masters),
            'create' => $create,
            'update' => $update,
            'unchanged' => $unchanged,
            'skipped' => $skipped,
            'local_only' => User::whereNotIn('id', $matchedIds)->count(),
        ];
    }

    /**
     * Terapkan snapshot DnD.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{created: int, updated: int, unchanged: int, notices: array<int, string>}
     */
    public function apply(array $snapshot): array
    {
        return DB::transaction(function () use ($snapshot) {
            $areaIds = $this->ensureMasters(Area::class, $snapshot['areas']);
            $divisiIds = $this->ensureMasters(Divisi::class, $snapshot['divisis']);
            $positionIds = $this->ensureMasters(Position::class, $snapshot['positions']);
            $roles = $this->ensureRoles($snapshot['roles']);

            $lookup = $this->localLookup();
            $created = $updated = $unchanged = 0;
            $notices = [];
            /** @var array<int, array{user: User, supervisor: array<string, mixed>|null}> $synced */
            $synced = [];

            foreach ($snapshot['users'] as $row) {
                $label = "{$row['nama_lengkap']} ({$row['employee_id']})";
                $user = $this->matchUser($row, $lookup);

                if ($user === false || $user?->trashed()) {
                    continue;
                }

                $attributes = [
                    'employee_id' => $row['employee_id'],
                    'nama_lengkap' => $row['nama_lengkap'],
                    'email' => $row['email'],
                    'area_id' => $row['area'] ? $areaIds[$this->key($row['area'])] ?? null : null,
                    'divisi_id' => $row['divisi'] ? $divisiIds[$this->key($row['divisi'])] ?? null : null,
                    'position_id' => $row['position'] ? $positionIds[$this->key($row['position'])] ?? null : null,
                ];

                if ($attributes['divisi_id'] === null) {
                    $notices[] = "{$label}: divisi kosong di DnD, user dilewati.";

                    continue;
                }

                if ($this->usernameAvailable($row['username'], $user)) {
                    $attributes['username'] = $row['username'];
                } elseif ($user === null) {
                    $notices[] = "{$label}: username \"{$row['username']}\" sudah dipakai user lain, user tidak dibuat.";

                    continue;
                } else {
                    $notices[] = "{$label}: username \"{$row['username']}\" sudah dipakai user lain, username tidak diubah.";
                }

                $phone = $row['no_hp'] ? WhatsAppNumber::toLocal($row['no_hp']) : null;
                if ($row['no_hp'] && (! $phone || ! $this->phoneAvailable($phone, $user))) {
                    $notices[] = "{$label}: No. HP {$row['no_hp']} tidak valid atau sudah dipakai user lain, tidak diubah.";
                } else {
                    $attributes['no_hp'] = $phone;
                }

                if ($user === null) {
                    $user = User::create([...$attributes, 'password' => Str::password(32)]);
                    $created++;
                } else {
                    $user->fill($attributes);
                    $dirty = $user->isDirty();
                    $user->save();

                    if ($dirty || ($row['role'] && $user->jabatan?->name !== $row['role'])) {
                        $updated++;
                    } else {
                        $unchanged++;
                    }
                }

                $role = $row['role'] ? $roles[$this->key($row['role'])] ?? null : null;
                if ($role && $user->jabatan?->name !== $role->name) {
                    $user->assignJabatan($role);
                    if ($role->name === User::ROLE_ADMIN && DataAccessService::isAreaScopedAdmin($user)) {
                        $notices[] = "{$label}: jabatan ADMIN dari DnD dibatasi ke badan usahanya sendiri.";
                    }
                }

                $lookup['employee'][$this->key((string) $user->employee_id)] ??= $user;
                $lookup['username'][$this->key($user->username)] ??= $user;
                $synced[] = ['user' => $user, 'supervisor' => $row['supervisor']];
            }

            $notices = [...$notices, ...$this->applyApprovalLines($synced, $lookup)];

            return compact('created', 'updated', 'unchanged', 'notices');
        });
    }

    /**
     * @param  array<int, array{user: User, supervisor: array<string, mixed>|null}>  $synced
     * @param  array{employee: array<string, User>, username: array<string, User>}  $lookup
     * @return array<int, string>
     */
    private function applyApprovalLines(array $synced, array $lookup): array
    {
        $notices = [];

        foreach ($synced as ['user' => $user, 'supervisor' => $supervisor]) {
            if ($supervisor === null) {
                continue;
            }

            $approver = $this->matchUser($supervisor, $lookup) ?: null;

            if (! $approver || $approver->trashed()) {
                $notices[] = "{$user->nama_lengkap}: atasan {$supervisor['nama_lengkap']} tidak ditemukan, approval line tidak diubah.";

                continue;
            }

            if ((int) $user->approval_id === (int) $approver->id) {
                continue;
            }

            if ($approver->id === $user->id || ApprovalScopeService::createsApprovalCycle($user->id, $approver->id)) {
                $notices[] = "{$user->nama_lengkap}: approval line ke {$approver->nama_lengkap} membentuk siklus, tidak diubah.";

                continue;
            }

            $user->update(['approval_id' => $approver->id]);
        }

        ApprovalScopeService::clearMemo();

        return $notices;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, array{from: string, to: string}>
     */
    private function changes(User $user, array $row): array
    {
        $current = [
            'Nama' => $user->nama_lengkap,
            'Employee ID' => $user->employee_id,
            'Username' => $user->username,
            'Email' => $user->email,
            'No. HP' => $user->no_hp,
            'Area' => $user->area?->name,
            'Divisi' => $user->divisi?->name,
            'Posisi' => $user->position?->name,
            'Jabatan' => $user->jabatan?->name,
            'Approval Line' => $user->approval?->nama_lengkap,
        ];
        $incoming = [
            'Nama' => $row['nama_lengkap'],
            'Employee ID' => $row['employee_id'],
            'Username' => $row['username'],
            'Email' => $row['email'],
            'No. HP' => $row['no_hp'] ? (WhatsAppNumber::toLocal($row['no_hp']) ?? $row['no_hp']) : null,
            'Area' => $row['area'],
            'Divisi' => $row['divisi'],
            'Posisi' => $row['position'],
            'Jabatan' => $row['role'],
            'Approval Line' => $row['supervisor']['nama_lengkap'] ?? null,
        ];

        $changes = [];
        foreach ($incoming as $field => $value) {
            if ($field === 'Approval Line' && $value === null) {
                continue;
            }

            if ($this->key((string) $value) !== $this->key((string) $current[$field])) {
                $changes[$field] = ['from' => (string) ($current[$field] ?? '-'), 'to' => (string) ($value ?? '-')];
            }
        }

        return $changes;
    }

    /**
     * @return array{employee: array<string, User>, username: array<string, User>}
     */
    private function localLookup(): array
    {
        $lookup = ['employee' => [], 'username' => []];

        foreach (User::withTrashed()->with(['roles', 'area', 'divisi', 'position', 'approval'])->get() as $user) {
            if (filled($user->employee_id)) {
                $lookup['employee'][$this->key($user->employee_id)] ??= $user;
            }
            $lookup['username'][$this->key($user->username)] ??= $user;
        }

        return $lookup;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{employee: array<string, User>, username: array<string, User>}  $lookup
     * @return User|null|false false bila Employee ID dan username menunjuk user berbeda.
     */
    private function matchUser(array $row, array $lookup): User|null|false
    {
        $byEmployee = filled($row['employee_id'] ?? null) ? $lookup['employee'][$this->key($row['employee_id'])] ?? null : null;
        $byUsername = filled($row['username'] ?? null) ? $lookup['username'][$this->key($row['username'])] ?? null : null;

        if ($byEmployee && $byUsername && $byEmployee->isNot($byUsername)) {
            return false;
        }

        return $byEmployee ?? $byUsername;
    }

    private function usernameAvailable(string $username, ?User $user): bool
    {
        return ! User::withTrashed()
            ->whereRaw('LOWER(username) = ?', [Str::lower($username)])
            ->when($user, fn ($query) => $query->whereKeyNot($user->id))
            ->exists();
    }

    private function phoneAvailable(string $phone, ?User $user): bool
    {
        return ! User::withTrashed()
            ->where('no_hp', $phone)
            ->when($user, fn ($query) => $query->whereKeyNot($user->id))
            ->exists();
    }

    /**
     * @param  class-string<Area|Divisi|Position>  $model
     * @param  array<int, string>  $names
     * @return array<string, int> nama kanonik => id
     */
    private function ensureMasters(string $model, array $names): array
    {
        $ids = $model::query()->orderBy('id')->get(['id', 'name'])
            ->reverse()
            ->mapWithKeys(fn ($record) => [$this->key($record->name) => (int) $record->id])
            ->all();

        foreach ($names as $name) {
            $ids[$this->key($name)] ??= (int) $model::create(['name' => $name])->id;
        }

        return $ids;
    }

    /**
     * Jabatan DnD baru dibuat dengan permission STAFF agar karyawannya tetap bisa memakai modul.
     *
     * @param  array<int, string>  $names
     * @return array<string, Role>
     */
    private function ensureRoles(array $names): array
    {
        $roles = Role::query()->where('guard_name', 'web')->get()->keyBy(fn (Role $role) => $this->key($role->name))->all();
        $staffPermissions = Role::findByName(User::ROLE_STAFF, 'web')->permissions;

        foreach ($names as $name) {
            $name = Str::upper($name);

            if (! isset($roles[$this->key($name)])) {
                $role = Role::create(['name' => $name, 'guard_name' => 'web', 'level' => Role::JABATAN[$name] ?? null]);
                $role->syncPermissions($staffPermissions);
                $roles[$this->key($name)] = $role;
            }
        }

        return $roles;
    }

    /**
     * @param  array<int, string>  $existing
     * @param  array<int, string>  $incoming
     * @return array<int, string>
     */
    private function missingNames(array $existing, array $incoming): array
    {
        $known = array_flip(array_map($this->key(...), $existing));

        return array_values(array_filter($incoming, fn (string $name) => ! isset($known[$this->key($name)])));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeUser(array $row): array
    {
        $supervisor = $row['supervisor'] ?? null;

        return [
            'employee_id' => filled($row['employee_id'] ?? null) ? trim((string) $row['employee_id']) : null,
            'username' => Str::lower(trim((string) ($row['username'] ?? ''))),
            'nama_lengkap' => Str::upper(Str::squish((string) ($row['nama_lengkap'] ?? ''))),
            'email' => filled($row['email'] ?? null) ? Str::lower(trim((string) $row['email'])) : null,
            'no_hp' => filled($row['no_hp'] ?? null) ? (string) $row['no_hp'] : null,
            'role' => isset($row['role']['name']) ? Str::upper(Str::squish($row['role']['name'])) : null,
            'area' => isset($row['area']['name']) ? Str::squish($row['area']['name']) : null,
            'divisi' => isset($row['divisi']['name']) ? Str::squish($row['divisi']['name']) : null,
            'position' => isset($row['position']['name']) ? Str::squish($row['position']['name']) : null,
            'supervisor' => is_array($supervisor) ? [
                'employee_id' => $supervisor['employee_id'] ?? null,
                'username' => Str::lower((string) ($supervisor['username'] ?? '')),
                'nama_lengkap' => (string) ($supervisor['nama_lengkap'] ?? ''),
            ] : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function names(array $rows): array
    {
        return collect($rows)->pluck('name')->filter()->map(fn ($name) => Str::squish((string) $name))->unique(fn ($name) => $this->key($name))->values()->all();
    }

    private function key(string $value): string
    {
        return Str::lower(Str::squish($value));
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl(rtrim((string) config('services.dnd.url'), '/').'/api/v1')
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);

        $token = config('services.dnd.token');

        if (blank($token)) {
            $response = $client->post('auth/login', [
                'login' => config('services.dnd.login'),
                'password' => config('services.dnd.password'),
                'device_name' => 'modul-web-sync',
            ]);

            $token = $response->json('data.token');
            if (! $response->successful() || blank($token)) {
                throw new RuntimeException('Login ke DnD gagal: '.($response->json('message') ?? 'HTTP '.$response->status()));
            }
        }

        return $client->withToken($token);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(PendingRequest $client, string $path, array $query = []): array
    {
        $response = $client->get($path, $query);

        if (! $response->successful() || ! is_array($response->json('data'))) {
            throw new RuntimeException("Gagal mengambil {$path} dari DnD: ".($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return $response->json();
    }
}
