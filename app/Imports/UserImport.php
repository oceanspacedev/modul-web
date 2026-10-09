<?php

namespace App\Imports;

use App\Models\Divisi;
use App\Models\JobLevel;
use App\Models\SubDivisi;
use App\Models\User;
use Exception;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UserImport implements ToModel, WithHeadingRow
{
    public function model(array $row): User
    {
        $user = new User;
        $user = $user->where('username', strtolower(str_replace('.', '', preg_replace('/\s+/', '', $row['username']))));

        if ($user->first()) {
            throw new Exception('username '.$row['username'].' sudah dipakai');
        } else {
            return new User([
                'id_karyawan' => ! empty($row['id_karyawan']) ? $row['id_karyawan'] : null,
                'full_name' => strtoupper($row['full_name']),
                'username' => strtolower(str_replace('.', '', preg_replace('/\s+/', '', $row['username']))),
                'email' => ! empty($row['email']) ? $row['email'] : null,
                'no_wa' => ! empty($row['no_wa']) ? $row['no_wa'] : null,
                'password' => ! empty($row['password']) ? $row['password'] : 'complete123',
                'divisi_id' => Divisi::where('name', preg_replace('/\s+/', '', $row['divisi']))->first()->id,
                'sub_divisi_id' => ! empty($row['sub_divisi']) ? SubDivisi::where('name', $row['sub_divisi'])->first()?->id : null,
                'job_level_id' => JobLevel::where('name', $row['job_level'])->first()->id,
            ]);
        }
    }
}
