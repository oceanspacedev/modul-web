<?php

namespace App\Imports;

use App\Models\Divisi;
use App\Models\Position;
use App\Models\Role;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Services\DataAccessService;
use App\Support\WhatsAppNumber;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Import peserta pelatihan dari Excel, sekaligus memutakhirkan data user yang cocok di master User.
 *
 * Setiap baris dicocokkan ke user yang sudah ada lewat Employee ID, lalu username, lalu No. HP (kolom pertama yang
 * terisi). Employee ID yang belum ada di master User dibuatkan user baru dari kolom template saja (Full Name, No HP,
 * Job Position, Divisi): jabatan STAFF, badan usaha mengikuti divisi, username = Employee ID, password acak (login
 * lewat OTP WhatsApp), approval line kosong. Sinkronisasi DnD mencocokkan lewat Employee ID, jadi user ini nanti
 * dimutakhirkan DnD, bukan diduplikasi. Hanya pengimpor dengan izin Kelola User yang bisa membuatnya, dan baris yang
 * No. HP atau username-nya sudah dipakai user lain ditolak. Tanpa Employee ID tidak dibuat user baru.
 *
 * No. HP wajib di setiap baris karena undangan dan link portal dikirim lewat WhatsApp. Nama, posisi, divisi, dan
 * No. HP user hanya diubah bila pengimpor boleh mengubah user itu (UserPolicy, mengikuti cakupan DataAccessService);
 * No. HP adalah login WhatsApp sehingga butuh izin changePhone. Yang tidak boleh diubah tetap dilaporkan, dan
 * pesertanya tetap ditambahkan.
 */
class TrainingParticipantImport implements ToCollection, WithHeadingRow
{
    private const EMPLOYEE_ID_COLUMNS = ['employee_id', 'id_karyawan', 'nik', 'nip'];

    private const USERNAME_COLUMNS = ['username'];

    private const NAME_COLUMNS = ['full_name', 'nama_lengkap', 'nama', 'name'];

    private const PHONE_COLUMNS = ['no_hp', 'nomor_hp', 'no_handphone', 'hp', 'phone', 'phone_number', 'mobile_phone', 'no_wa', 'nomor_wa', 'whatsapp', 'no_whatsapp', 'no_telepon', 'telepon'];

    private const POSITION_COLUMNS = ['job_position', 'position', 'posisi'];

    private const DIVISI_COLUMNS = ['divisi', 'division'];

    /** @var list<string> baris yang ditolak */
    private array $errors = [];

    /** @var list<string> baris yang diproses dengan catatan (mis. data user tidak diubah) */
    private array $notes = [];

    private int $addedCount = 0;

    private int $existingCount = 0;

    private int $updatedUserCount = 0;

    private int $createdUserCount = 0;

    /** @var list<int> peserta baru yang ditambahkan import ini */
    private array $addedParticipantIds = [];

    /** @var array<int, int> id user => baris yang sudah memprosesnya di file ini */
    private array $seenUsers = [];

    public function __construct(private Training $training, private User $actor) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            throw new Exception('File tidak berisi data peserta. Isi minimal satu baris peserta di bawah judul kolom.');
        }

        $headings = array_keys($rows->first()->toArray());

        if (array_intersect(self::PHONE_COLUMNS, $headings) === []) {
            throw new Exception('Kolom No. HP tidak ditemukan. Tambahkan kolom "No HP" berisi nomor WhatsApp peserta (wajib, dipakai untuk undangan dan link portal), atau gunakan template import peserta.');
        }

        foreach ($rows as $index => $row) {
            // Baris 1 adalah judul kolom.
            $rowNumber = $index + 2;
            $row = $row->toArray();

            if (collect($row)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            try {
                DB::transaction(fn () => $this->importRow($row, $rowNumber));
            } catch (ImportRowRejected $e) {
                $this->errors[] = "Baris {$rowNumber}: {$e->getMessage()}";
            } catch (Throwable $e) {
                // Pesan teknis (mis. SQL) tidak ditampilkan ke pengimpor; rinciannya masuk log.
                report($e);
                $this->errors[] = "Baris {$rowNumber}: gagal disimpan karena kesalahan sistem. Periksa isi barisnya (mis. teks terlalu panjang) lalu coba lagi.";
            }
        }
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<string>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    public function addedCount(): int
    {
        return $this->addedCount;
    }

    public function existingCount(): int
    {
        return $this->existingCount;
    }

    public function updatedUserCount(): int
    {
        return $this->updatedUserCount;
    }

    public function createdUserCount(): int
    {
        return $this->createdUserCount;
    }

    /**
     * @return list<int>
     */
    public function addedParticipantIds(): array
    {
        return $this->addedParticipantIds;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importRow(array $row, int $rowNumber): void
    {
        $phone = $this->phone($this->value($row, self::PHONE_COLUMNS));
        $user = $this->findUser($row, $phone);
        $created = $user === null;

        if ($created) {
            [$user, $notes] = $this->createUser($row, $phone);
            $updated = false;
        }

        $label = $user->nama_lengkap.($user->employee_id ? " ({$user->employee_id})" : '');

        if ($user->trashed()) {
            throw new ImportRowRejected("{$label} sudah dinonaktifkan di master User. Pulihkan dulu di Pengaturan > User bila memang masih aktif");
        }

        if (isset($this->seenUsers[$user->id])) {
            throw new ImportRowRejected("{$label} sudah ada di baris {$this->seenUsers[$user->id]} file yang sama");
        }

        if (! $created) {
            [$notes, $updated] = $this->updateUser($user, $row, $phone);
        }

        if (blank($user->no_hp)) {
            throw new ImportRowRejected("{$label} tidak punya No. HP di master User, jadi tidak bisa menerima undangan WhatsApp. ".implode(' ', $notes));
        }

        $participant = $this->training->participants()->where('user_id', $user->id)->first();
        if ($participant) {
            $this->existingCount++;
        } else {
            $participant = TrainingParticipant::createFor($this->training, $user->id);
            $this->addedParticipantIds[] = $participant->id;
            $this->addedCount++;
        }

        // Dicatat setelah barisnya benar-benar tersimpan: baris yang ditolak tidak ikut dihitung dan tidak membuat
        // baris berikutnya untuk user yang sama dianggap dobel.
        $this->seenUsers[$user->id] = $rowNumber;
        if ($updated) {
            $this->updatedUserCount++;
        }
        if ($created) {
            $this->createdUserCount++;
        }
        if ($notes !== []) {
            $this->notes[] = "Baris {$rowNumber} ({$label}, tetap menjadi peserta): ".implode(' ', $notes);
        }
    }

    /**
     * User dari kolom pengenal pertama yang terisi: Employee ID, username, lalu No. HP. Tidak menebak ke kolom
     * berikutnya bila kolom yang terisi tidak cocok, supaya baris tidak terpasang ke orang lain. Null bila Employee ID
     * belum ada di master User (calon user baru).
     *
     * @param  array<string, mixed>  $row
     */
    private function findUser(array $row, string $phone): ?User
    {
        $employeeId = $this->text($this->value($row, self::EMPLOYEE_ID_COLUMNS));
        if ($employeeId !== '') {
            if (! User::withTrashed()->where('employee_id', $employeeId)->exists()) {
                return null;
            }

            return $this->single(
                User::withTrashed()->where('employee_id', $employeeId),
                "Employee ID {$employeeId} tidak ditemukan di master User. Tambahkan karyawannya lewat Pengaturan > User atau sinkronisasi DnD, lalu impor ulang",
                "Employee ID {$employeeId} dipakai lebih dari satu user di master User. Rapikan datanya di Pengaturan > User",
            );
        }

        $username = Str::lower($this->text($this->value($row, self::USERNAME_COLUMNS)));
        if ($username !== '') {
            return $this->single(
                User::withTrashed()->whereRaw('LOWER(username) = ?', [$username]),
                "Username {$username} tidak ditemukan di master User",
                "Username {$username} dipakai lebih dari satu user",
            );
        }

        // User aktif didahulukan: nomor yang juga tersimpan di user nonaktif tidak membuat barisnya dianggap ganda.
        return $this->single(
            User::query()->where('no_hp', $phone)->exists() ? User::query()->where('no_hp', $phone) : User::withTrashed()->where('no_hp', $phone),
            "No. HP {$phone} tidak ditemukan di master User. Isi kolom Employee ID supaya peserta bisa dicocokkan atau dibuatkan user baru",
            "No. HP {$phone} dipakai lebih dari satu user. Isi kolom Employee ID supaya peserta bisa dicocokkan",
        );
    }

    /**
     * @param  Builder<User>  $query
     */
    private function single($query, string $missing, string $ambiguous): User
    {
        $users = $query->limit(2)->get();

        return match ($users->count()) {
            0 => throw new ImportRowRejected($missing),
            1 => $users->first(),
            default => throw new ImportRowRejected($ambiguous),
        };
    }

    /**
     * Buat user baru dari kolom template untuk Employee ID yang belum ada. Mengembalikan user dan catatan untuk data
     * yang tidak bisa dipakai (mis. posisi baru tanpa izin master data).
     *
     * @param  array<string, mixed>  $row
     * @return array{0: User, 1: list<string>}
     */
    private function createUser(array $row, string $phone): array
    {
        $employeeId = $this->text($this->value($row, self::EMPLOYEE_ID_COLUMNS));
        $missing = "Employee ID {$employeeId} tidak ditemukan di master User";

        if (! Gate::forUser($this->actor)->allows('create', User::class)) {
            throw new ImportRowRejected("{$missing}. Hanya pengguna dengan izin Kelola User yang dapat membuat user baru lewat import; tambahkan karyawannya lewat Pengaturan > User atau sinkronisasi DnD, lalu impor ulang");
        }

        $name = Str::upper(Str::squish($this->text($this->value($row, self::NAME_COLUMNS))));
        if ($name === '') {
            throw new ImportRowRejected("{$missing}; isi kolom Full Name supaya user baru bisa dibuat");
        }

        $divisi = $this->divisiForNewUser($missing, Str::squish($this->text($this->value($row, self::DIVISI_COLUMNS))));

        $username = Str::lower(preg_replace('/\s+/', '', $employeeId));
        $usernameOwner = User::withTrashed()->whereRaw('LOWER(username) = ?', [$username])->first();
        if ($usernameOwner) {
            throw new ImportRowRejected("{$missing} dan username \"{$username}\" untuk user barunya sudah dipakai {$usernameOwner->nama_lengkap}. Periksa Employee ID-nya");
        }

        $phoneOwner = User::withTrashed()->where('no_hp', $phone)->first();
        if ($phoneOwner) {
            throw new ImportRowRejected("{$missing} dan No. HP {$phone} sudah dipakai {$phoneOwner->nama_lengkap}"
                .($phoneOwner->employee_id ? " ({$phoneOwner->employee_id})" : '')
                .($phoneOwner->trashed() ? ' (nonaktif)' : '').'. Periksa Employee ID atau No. HP-nya, user baru tidak dibuat');
        }

        $notes = [];
        $positionId = null;
        $positionName = Str::squish($this->text($this->value($row, self::POSITION_COLUMNS)));
        if ($positionName !== '') {
            $position = Position::query()->whereRaw('LOWER(name) = ?', [Str::lower($positionName)])->first();
            if (! $position && Gate::forUser($this->actor)->allows('manage-master-data')) {
                $position = Position::create(['name' => $positionName]);
            }
            if ($position) {
                $positionId = $position->id;
            } else {
                $notes[] = "Posisi \"{$positionName}\" tidak ada di master Posisi dan Anda tidak berhak menambah master data, posisi user baru dikosongkan.";
            }
        }

        $user = User::create([
            'employee_id' => $employeeId,
            'nama_lengkap' => $name,
            'username' => $username,
            'no_hp' => $phone,
            'area_id' => $divisi->area_id,
            'divisi_id' => $divisi->id,
            'position_id' => $positionId,
            // Login peserta lewat OTP WhatsApp; password bisa diatur nanti di Pengaturan > User.
            'password' => Str::password(32),
        ]);
        $user->assignJabatan(Role::findByName(User::ROLE_STAFF, 'web'));

        return [$user, $notes];
    }

    /**
     * Divisi user baru dari kolom Divisi (wajib), di badan usaha yang boleh diakses pengimpor. Nama divisi yang sama
     * di beberapa badan usaha diutamakan yang sebadan usaha dengan pengimpor.
     */
    private function divisiForNewUser(string $missing, string $divisiName): Divisi
    {
        if ($divisiName === '') {
            throw new ImportRowRejected("{$missing}; isi kolom Divisi supaya user baru bisa dibuat");
        }

        $divisis = Divisi::query()->whereRaw('LOWER(name) = ?', [Str::lower($divisiName)])->get();
        if ($divisis->isEmpty()) {
            throw new ImportRowRejected("{$missing} dan Divisi \"{$divisiName}\" tidak ada di master Divisi, jadi user baru tidak dibuat");
        }

        $accessible = $divisis->filter(fn (Divisi $divisi) => ! DataAccessService::isAreaScopedAdmin($this->actor)
            || DataAccessService::canAccessArea($this->actor, $divisi->area_id));
        if ($accessible->isEmpty()) {
            throw new ImportRowRejected("{$missing} dan Divisi \"{$divisiName}\" berada di luar cakupan badan usaha Anda, jadi user baru tidak dibuat");
        }

        $ownArea = $accessible->firstWhere('area_id', $this->actor->area_id);
        if ($ownArea) {
            return $ownArea;
        }
        if ($accessible->count() > 1) {
            throw new ImportRowRejected("{$missing} dan Divisi \"{$divisiName}\" ada di beberapa badan usaha, jadi user baru tidak dibuat. Tambahkan karyawannya lewat Pengaturan > User");
        }

        return $accessible->first();
    }

    /**
     * Samakan data master user dengan file. Mengembalikan catatan untuk data yang berbeda tetapi tidak diubah, dan
     * apakah ada data user yang diubah.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: list<string>, 1: bool}
     */
    private function updateUser(User $user, array $row, string $phone): array
    {
        $notes = [];
        $changes = [];

        $name = Str::upper(Str::squish($this->text($this->value($row, self::NAME_COLUMNS))));
        if ($name !== '' && $name !== $user->nama_lengkap) {
            $changes['nama_lengkap'] = $name;
        }

        $positionName = Str::squish($this->text($this->value($row, self::POSITION_COLUMNS)));
        if ($positionName !== '') {
            $position = Position::query()->whereRaw('LOWER(name) = ?', [Str::lower($positionName)])->first();
            if (! $position && ! Gate::forUser($this->actor)->allows('manage-master-data')) {
                $notes[] = "Posisi \"{$positionName}\" tidak ada di master Posisi dan Anda tidak berhak menambah master data, posisi user tidak diubah.";
            } elseif (! $position) {
                $changes['position_id'] = $positionName;
            } elseif ((int) $position->id !== (int) $user->position_id) {
                $changes['position_id'] = $position;
            }
        }

        $divisiName = Str::squish($this->text($this->value($row, self::DIVISI_COLUMNS)));
        if ($divisiName !== '') {
            $divisi = Divisi::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($divisiName)])
                ->orderByRaw('CASE WHEN area_id = ? THEN 0 ELSE 1 END', [(int) $user->area_id])
                ->first();

            if (! $divisi) {
                $notes[] = "Divisi \"{$divisiName}\" tidak ada di master Divisi, divisi user tidak diubah.";
            } elseif ((int) $divisi->id !== (int) $user->divisi_id) {
                $changes['divisi_id'] = $divisi->id;
            }
        }

        $phoneChanged = $phone !== $user->no_hp;
        if ($phoneChanged) {
            // Termasuk user nonaktif, sama seperti form User: nomornya kembali dipakai login bila user itu dipulihkan.
            $owner = User::withTrashed()->where('no_hp', $phone)->whereKeyNot($user->id)->first();

            if ($owner) {
                $notes[] = "No. HP {$phone} sudah dipakai {$owner->nama_lengkap}".($owner->trashed() ? ' (nonaktif)' : '').', No. HP user tidak diubah.';
                $phoneChanged = false;
            } elseif (! Gate::forUser($this->actor)->allows('changePhone', $user)) {
                $notes[] = "No. HP di file ({$phone}) berbeda dengan master User ("
                    .($user->no_hp ?: 'kosong')
                    .'). No. HP adalah login WhatsApp dan hanya bisa diubah admin yang berwenang atas user ini, jadi tidak diubah.';
                $phoneChanged = false;
            }
        }

        if ($changes !== [] && ! Gate::forUser($this->actor)->allows('update', $user)) {
            $notes[] = 'Nama/posisi/divisi di file berbeda dengan master User, tetapi user ini di luar cakupan akses data Anda, jadi tidak diubah.';
            $changes = [];
        }

        if (is_string($changes['position_id'] ?? null)) {
            $changes['position_id'] = Position::create(['name' => $changes['position_id']])->id;
        } elseif (isset($changes['position_id'])) {
            $changes['position_id'] = $changes['position_id']->id;
        }

        if ($phoneChanged) {
            $changes['no_hp'] = $phone;
        }

        if ($changes !== []) {
            $user->update($changes);
        }

        return [$notes, $changes !== []];
    }

    private function phone(mixed $value): string
    {
        $value = $this->text($value);

        if ($value === '') {
            throw new ImportRowRejected('No. HP wajib diisi karena undangan dan link portal dikirim lewat WhatsApp');
        }

        return WhatsAppNumber::toLocal($value)
            ?? throw new ImportRowRejected("No. HP \"{$value}\" bukan nomor WhatsApp Indonesia yang valid");
    }

    /**
     * Isi sel sebagai teks. Angka besar (No. HP yang diketik sebagai angka) tidak boleh berubah menjadi notasi E.
     */
    private function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // Hanya angka bulat yang ditulis tanpa desimal; ID seperti 2019.01 tidak boleh dibulatkan menjadi 2019.
        return trim(is_float($value) && floor($value) === $value ? sprintf('%.0f', $value) : (string) $value);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function value(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return $row[$key];
            }
        }

        return null;
    }
}
