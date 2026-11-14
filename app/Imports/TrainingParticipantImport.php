<?php

namespace App\Imports;

use App\Models\Divisi;
use App\Models\Position;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
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
 * terisi). User yang belum ada tidak dibuat: master User bersumber dari DnD dan butuh jabatan, badan usaha, dan
 * approval yang tidak ada di file peserta, jadi barisnya ditolak dengan alasannya.
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
        $label = $user->nama_lengkap.($user->employee_id ? " ({$user->employee_id})" : '');

        if ($user->trashed()) {
            throw new ImportRowRejected("{$label} sudah dinonaktifkan di master User. Pulihkan dulu di Pengaturan > User bila memang masih aktif");
        }

        if (isset($this->seenUsers[$user->id])) {
            throw new ImportRowRejected("{$label} sudah ada di baris {$this->seenUsers[$user->id]} file yang sama");
        }

        [$notes, $updated] = $this->updateUser($user, $row, $phone);

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
        if ($notes !== []) {
            $this->notes[] = "Baris {$rowNumber} ({$label}, tetap menjadi peserta): ".implode(' ', $notes);
        }
    }

    /**
     * User dari kolom pengenal pertama yang terisi: Employee ID, username, lalu No. HP. Tidak menebak ke kolom
     * berikutnya bila kolom yang terisi tidak cocok, supaya baris tidak terpasang ke orang lain.
     *
     * @param  array<string, mixed>  $row
     */
    private function findUser(array $row, string $phone): User
    {
        $employeeId = $this->text($this->value($row, self::EMPLOYEE_ID_COLUMNS));
        if ($employeeId !== '') {
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
            "No. HP {$phone} tidak ditemukan di master User. Isi kolom Employee ID supaya peserta bisa dicocokkan",
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
