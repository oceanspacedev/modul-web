<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyamakan modul user dengan DnD:
 * - users: id_karyawan/full_name/no_wa menjadi employee_id/nama_lengkap/no_hp, ditambah area, posisi,
 *   approval line, dan akses data badan usaha.
 * - Job level digabung ke role Spatie (jabatan DnD); sub divisi dihapus; master area & posisi ditambahkan.
 * - Role "Trainer" menjadi hak akses tambahan (permission langsung) karena user DnD hanya punya satu jabatan.
 */
return new class extends Migration
{
    /** Jabatan DnD beserta level grade-nya. */
    private const JABATAN = [
        'ADMIN' => 100,
        'STAFF' => 10,
        'TEAM LEADER' => 20,
        'COORDINATOR' => 30,
        'MANAGER' => 40,
        'CHIEF' => 50,
        'BOD' => 60,
    ];

    private const TRAINER_PERMISSIONS = ['manage-trainings', 'manage-videos', 'manage-documents'];

    private const USER_MODEL = 'App\\Models\\User';

    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('divisis', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('id');
            $table->foreignId('manager_id')->nullable()->after('name');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedInteger('level')->nullable()->after('name');
            $table->boolean('requires_approval')->default(false)->after('level');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('id_karyawan', 'employee_id');
            $table->renameColumn('full_name', 'nama_lengkap');
            $table->renameColumn('no_wa', 'no_hp');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('data_access', 20)->default('all')->after('password');
            $table->foreignId('area_id')->nullable()->after('data_access');
            $table->foreignId('position_id')->nullable()->after('divisi_id');
            $table->foreignId('approval_id')->nullable()->after('position_id');
        });

        $this->addEmployeeIdUniqueIndex();

        Schema::create('user_area_access', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'area_id']);
            $table->index('area_id', 'user_area_access_area_id_index');
        });

        $roleIds = $this->createJabatanRoles();
        $this->moveUsersToJabatan($roleIds);
        $this->moveDocumentsToJabatan($roleIds);
        $this->normalizePhoneNumbers();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sub_divisi_id', 'job_level_id']);
        });

        Schema::dropIfExists('sub_divisis');
        Schema::dropIfExists('job_levels');

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Schema::create('job_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sub_divisis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('divisi_id');
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (array_keys(self::JABATAN) as $name) {
            DB::table('job_levels')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('job_levels')->insert(['name' => 'ALL', 'created_at' => now(), 'updated_at' => now()]);
        $jobLevelIds = DB::table('job_levels')->pluck('id', 'name');

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('sub_divisi_id')->nullable()->after('divisi_id');
            $table->foreignId('job_level_id')->nullable()->after('sub_divisi_id');
        });

        foreach (DB::table('documents')->get(['id', 'role_id']) as $document) {
            $roleName = $document->role_id ? DB::table('roles')->where('id', $document->role_id)->value('name') : null;
            DB::table('documents')->where('id', $document->id)->update([
                'job_level_id' => $jobLevelIds[$roleName ?? 'ALL'] ?? $jobLevelIds['ALL'],
            ]);
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('documents_name_divisi_id_role_id_path_unique');
            $table->dropColumn('role_id');
            $table->unique(['name', 'divisi_id', 'job_level_id', 'path']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sub_divisi_id')->nullable()->after('divisi_id');
            $table->foreignId('job_level_id')->nullable()->after('sub_divisi_id');
        });

        foreach (DB::table('model_has_roles')->where('model_type', self::USER_MODEL)->get() as $assignment) {
            $roleName = DB::table('roles')->where('id', $assignment->role_id)->value('name');
            DB::table('users')->where('id', $assignment->model_id)->update([
                'job_level_id' => $jobLevelIds[$roleName] ?? $jobLevelIds['STAFF'],
            ]);
        }

        Schema::dropIfExists('user_area_access');

        if (Schema::hasIndex('users', 'users_employee_id_unique')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_employee_id_unique'));
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['data_access', 'area_id', 'position_id', 'approval_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('employee_id', 'id_karyawan');
            $table->renameColumn('nama_lengkap', 'full_name');
            $table->renameColumn('no_hp', 'no_wa');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['level', 'requires_approval']);
        });

        Schema::table('divisis', function (Blueprint $table) {
            $table->dropColumn(['area_id', 'manager_id']);
        });

        Schema::dropIfExists('positions');
        Schema::dropIfExists('areas');

        app('cache')->forget(config('permission.cache.key'));
    }

    /**
     * Unique seperti DnD, hanya bila data lama tidak punya ID karyawan ganda.
     */
    private function addEmployeeIdUniqueIndex(): void
    {
        DB::table('users')->where('employee_id', '')->update(['employee_id' => null]);

        $hasDuplicates = DB::table('users')
            ->whereNotNull('employee_id')
            ->select('employee_id')
            ->groupBy('employee_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('employee_id');
        });
    }

    /**
     * Role Spatie bawaan diganti dengan jabatan DnD. Role "Admin"/"Staff" diganti nama agar permission-nya terbawa,
     * jabatan lain mendapat permission STAFF. Role buatan sendiri dibiarkan.
     *
     * @return array<string, int> nama jabatan => role id
     */
    private function createJabatanRoles(): array
    {
        $now = now();

        // Dibandingkan di PHP karena collation MySQL tidak membedakan huruf besar/kecil ("Admin" = "ADMIN").
        $existing = DB::table('roles')->where('guard_name', 'web')->pluck('name', 'id');
        foreach ($existing as $id => $name) {
            $upper = mb_strtoupper(trim($name));

            if (isset(self::JABATAN[$upper]) && $name !== $upper && ! $existing->contains($upper)) {
                DB::table('roles')->where('id', $id)->update(['name' => $upper]);
            }
        }

        foreach (self::JABATAN as $name => $level) {
            $exists = DB::table('roles')->where('guard_name', 'web')->pluck('name')->contains($name);

            if (! $exists) {
                DB::table('roles')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('roles')->where('name', $name)->where('guard_name', 'web')->update(['level' => $level]);
        }

        $roleIds = DB::table('roles')->where('guard_name', 'web')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();

        $staffPermissionIds = DB::table('role_has_permissions')->where('role_id', $roleIds['STAFF'])->pluck('permission_id');
        foreach (['TEAM LEADER', 'COORDINATOR', 'MANAGER', 'CHIEF', 'BOD'] as $name) {
            if (DB::table('role_has_permissions')->where('role_id', $roleIds[$name])->exists()) {
                continue;
            }

            DB::table('role_has_permissions')->insert(
                $staffPermissionIds->map(fn ($permissionId) => ['permission_id' => $permissionId, 'role_id' => $roleIds[$name]])->all()
            );
        }

        DB::table('role_has_permissions')->insertOrIgnore(
            DB::table('permissions')->where('guard_name', 'web')->pluck('id')
                ->map(fn ($permissionId) => ['permission_id' => $permissionId, 'role_id' => $roleIds['ADMIN']])->all()
        );

        return $roleIds;
    }

    /**
     * Setiap user mendapat tepat satu jabatan: dari job level-nya (ADMIN tetap ADMIN bila role Spatie-nya Admin),
     * job level lain (mis. "ALL") menjadi STAFF. Pemegang role Trainer mendapat permission trainer langsung.
     *
     * @param  array<string, int>  $roleIds
     */
    private function moveUsersToJabatan(array $roleIds): void
    {
        $jobLevelNames = DB::table('job_levels')->pluck('name', 'id')->map(fn ($name) => mb_strtoupper(trim($name)));
        $trainerRoleId = $roleIds['Trainer'] ?? null;
        $trainerPermissionIds = DB::table('permissions')->where('guard_name', 'web')
            ->whereIn('name', self::TRAINER_PERMISSIONS)->pluck('id');
        $jabatanRoleIds = array_map(fn ($name) => $roleIds[$name], array_keys(self::JABATAN));

        foreach (DB::table('users')->get(['id', 'job_level_id']) as $user) {
            $assignedRoleIds = DB::table('model_has_roles')
                ->where('model_type', self::USER_MODEL)
                ->where('model_id', $user->id)
                ->pluck('role_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $jabatan = $jobLevelNames[$user->job_level_id] ?? null;
            if (in_array($roleIds['ADMIN'], $assignedRoleIds, true)) {
                $jabatan = 'ADMIN';
            } elseif (! isset(self::JABATAN[$jabatan])) {
                $jabatan = 'STAFF';
            }

            if ($trainerRoleId && in_array($trainerRoleId, $assignedRoleIds, true)) {
                DB::table('model_has_permissions')->insertOrIgnore(
                    $trainerPermissionIds->map(fn ($permissionId) => [
                        'permission_id' => $permissionId,
                        'model_type' => self::USER_MODEL,
                        'model_id' => $user->id,
                    ])->all()
                );
            }

            DB::table('model_has_roles')
                ->where('model_type', self::USER_MODEL)
                ->where('model_id', $user->id)
                ->where(fn ($query) => $query->whereIn('role_id', $jabatanRoleIds)->orWhere('role_id', $trainerRoleId))
                ->delete();

            DB::table('model_has_roles')->insert([
                'role_id' => $roleIds[$jabatan],
                'model_type' => self::USER_MODEL,
                'model_id' => $user->id,
            ]);
        }

        if ($trainerRoleId) {
            DB::table('role_has_permissions')->where('role_id', $trainerRoleId)->delete();
            DB::table('model_has_roles')->where('role_id', $trainerRoleId)->delete();
            DB::table('roles')->where('id', $trainerRoleId)->delete();
        }
    }

    /**
     * Dokumen menargetkan jabatan (role_id), bukan job level. Job level "ALL" menjadi semua jabatan (null).
     *
     * @param  array<string, int>  $roleIds
     */
    private function moveDocumentsToJabatan(array $roleIds): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('divisi_id');
        });

        $jobLevelNames = DB::table('job_levels')->pluck('name', 'id')->map(fn ($name) => mb_strtoupper(trim($name)));

        foreach (DB::table('documents')->whereNotNull('job_level_id')->get(['id', 'job_level_id']) as $document) {
            $jabatan = $jobLevelNames[$document->job_level_id] ?? null;

            DB::table('documents')->where('id', $document->id)->update([
                'role_id' => isset(self::JABATAN[$jabatan]) ? $roleIds[$jabatan] : null,
            ]);
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('documents_name_divisi_id_job_level_id_path_unique');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['sub_divisi_id', 'job_level_id']);
            $table->unique(['name', 'divisi_id', 'role_id', 'path']);
        });
    }

    /**
     * No. HP disimpan dalam format lokal 08xx seperti DnD; nomor yang tidak valid dibiarkan apa adanya.
     */
    private function normalizePhoneNumbers(): void
    {
        foreach (DB::table('users')->whereNotNull('no_hp')->get(['id', 'no_hp']) as $user) {
            $digits = preg_replace('/\D+/', '', (string) $user->no_hp) ?? '';

            if (str_starts_with($digits, '0062')) {
                $digits = '0'.substr($digits, 4);
            } elseif (str_starts_with($digits, '62')) {
                $digits = '0'.substr($digits, 2);
            } elseif (str_starts_with($digits, '8')) {
                $digits = '0'.$digits;
            }

            if (preg_match('/^08\d{8,12}$/', $digits) === 1 && $digits !== $user->no_hp) {
                DB::table('users')->where('id', $user->id)->update(['no_hp' => $digits]);
            }
        }
    }
};
