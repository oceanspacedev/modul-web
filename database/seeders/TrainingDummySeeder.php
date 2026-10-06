<?php

namespace Database\Seeders;

use App\Models\Divisi;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TrainingDummySeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Pastikan ada divisi ────────────────────────────────────────
        $divisiIds = Divisi::pluck('id')->toArray();
        if (empty($divisiIds)) {
            $this->command->error('Divisi kosong. Jalankan DivisiSeeder dulu.');
            return;
        }

        // ── 2. Buat 30 user peserta dummy ─────────────────────────────────
        $this->command->info('Membuat 30 user peserta dummy...');

        $namaList = [
            'Andi Saputra','Budi Santoso','Citra Dewi','Deni Firmansyah','Eka Putri',
            'Fajar Nugroho','Gita Rahayu','Hendra Wijaya','Indah Permata','Joko Susilo',
            'Kartini Sari','Lukman Hakim','Maya Anggraini','Nanda Pratama','Oktavia Wulandari',
            'Pandu Kusuma','Qori Amalia','Rizky Hidayat','Sari Utami','Taufik Rahman',
            'Umar Abdullah','Vina Melati','Wahyu Setiawan','Xena Kristiani','Yudi Prasetyo',
            'Zahra Nadia','Arif Budiman','Bella Safitri','Cahyo Pramono','Dewi Lestari',
        ];

        $users = [];
        foreach ($namaList as $i => $nama) {
            $idKaryawan = 'KRY-DUM-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT);

            // Skip kalau sudah ada
            $existing = User::where('id_karyawan', $idKaryawan)->first();
            if ($existing) {
                $users[] = $existing;
                continue;
            }

            $users[] = User::create([
                'full_name'      => $nama,
                'username'       => 'dummy_' . Str::slug($nama, '_'),
                'id_karyawan'    => $idKaryawan,
                'email'          => 'dummy_' . Str::slug($nama, '_') . '@test.com',
                'no_wa'          => '08' . rand(1000000000, 9999999999),
                'password'       => Hash::make('password123'),
                'divisi_id'      => $divisiIds[array_rand($divisiIds)],
                'job_level_id'   => 1,
            ]);
        }

        $this->command->info('30 user berhasil dibuat/ditemukan.');

        // ── 3. Buat training dummy ────────────────────────────────────────
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))
            ->first() ?? User::first();

        $this->command->info('Membuat training dummy...');

        $training = Training::create([
            'title'                   => 'DUMMY - Pelatihan K3 & Keselamatan Kerja',
            'trainer_id'              => $admin->id,
            'description'             => 'Pelatihan wajib tahunan tentang K3, prosedur keselamatan, dan penanganan darurat di tempat kerja.',
            'training_date'           => now()->addDays(3)->format('Y-m-d'),
            'start_time'              => '09:00:00',
            'end_time'                => '11:00:00',
            'zoom_link'               => 'https://zoom.us/j/dummy123456',
            'status'                  => 'scheduled',
            'is_quiz_active'          => false,
            'is_attendance_active'    => false,
            'require_attendance_proof'=> false,
            'quiz_mode'               => 'formal',
        ]);

        // ── 4. Daftarkan 30 peserta ───────────────────────────────────────
        $this->command->info('Mendaftarkan 30 peserta ke training...');

        $participants = [];
        foreach ($users as $user) {
            $participants[] = TrainingParticipant::create([
                'training_id'       => $training->id,
                'user_id'           => $user->id,
                'token'             => Str::random(40) . '_' . time() . '_' . $user->id,
                'attendance_status' => 'pending',
            ]);
        }

        // ── 5. Buat soal kuis (5 PG + 1 essay) ───────────────────────────
        $this->command->info('Membuat soal kuis dummy...');

        $questions = [
            [
                'question'       => 'APD yang wajib digunakan saat bekerja di area produksi adalah?',
                'type'           => 'multiple_choice',
                'option_a'       => 'Helm, sepatu safety, rompi',
                'option_b'       => 'Sandal, kaos biasa',
                'option_c'       => 'Jas, dasi, sepatu pantofel',
                'option_d'       => 'Tidak perlu APD',
                'correct_answer' => 'a',
                'explanation'    => 'APD standar mencakup helm, sepatu safety, dan rompi keselamatan.',
            ],
            [
                'question'       => 'Jalur evakuasi harus ditandai dengan warna?',
                'type'           => 'multiple_choice',
                'option_a'       => 'Merah',
                'option_b'       => 'Hijau',
                'option_c'       => 'Kuning',
                'option_d'       => 'Biru',
                'correct_answer' => 'b',
                'explanation'    => 'Jalur evakuasi menggunakan warna hijau sesuai standar internasional.',
            ],
            [
                'question'       => 'APAR singkatan dari?',
                'type'           => 'multiple_choice',
                'option_a'       => 'Alat Pemadam Api Ringan',
                'option_b'       => 'Alat Pencegah Api Rambat',
                'option_c'       => 'Alat Pemadam Asap Ruangan',
                'option_d'       => 'Alat Pengaman Api Reaktif',
                'correct_answer' => 'a',
                'explanation'    => 'APAR = Alat Pemadam Api Ringan.',
            ],
            [
                'question'       => 'Jika terjadi kecelakaan kerja, langkah pertama yang harus dilakukan adalah?',
                'type'           => 'multiple_choice',
                'option_a'       => 'Lari keluar gedung',
                'option_b'       => 'Mengamankan area dan segera melapor ke supervisor',
                'option_c'       => 'Diam dan tunggu orang lain',
                'option_d'       => 'Upload ke media sosial',
                'correct_answer' => 'b',
                'explanation'    => 'Prosedur pertama adalah amankan area lalu lapor ke supervisor.',
            ],
            [
                'question'       => 'Berapa jarak aman minimal APAR dari titik api saat memadamkan?',
                'type'           => 'multiple_choice',
                'option_a'       => '1 meter',
                'option_b'       => '5 meter',
                'option_c'       => '2-3 meter',
                'option_d'       => '10 meter',
                'correct_answer' => 'c',
                'explanation'    => 'Jarak aman penggunaan APAR adalah 2-3 meter dari titik api.',
            ],
            [
                'question'       => 'Jelaskan secara singkat apa yang dimaksud dengan Hazard Identification dan mengapa hal ini penting dalam lingkungan kerja!',
                'type'           => 'essay',
                'option_a'       => null,
                'option_b'       => null,
                'option_c'       => null,
                'option_d'       => null,
                'correct_answer' => 'Hazard Identification adalah proses mengidentifikasi bahaya potensial di tempat kerja sebelum terjadi kecelakaan, penting untuk mencegah cedera dan kerugian.',
                'explanation'    => null,
            ],
        ];

        $questionModels = [];
        foreach ($questions as $qData) {
            $questionModels[] = TrainingQuestion::create(array_merge(
                $qData,
                ['training_id' => $training->id]
            ));
        }

        // ── 6. Simulasi status peserta yang bervariasi ────────────────────
        $this->command->info('Mensimulasikan kondisi peserta yang bervariasi...');

        // Bagi peserta menjadi beberapa kelompok
        $hadirCount      = 18; // 18 hadir
        $tidakHadirCount = 5;  // 5 tidak hadir
        // sisanya 7 = pending (belum absen)

        $mcQuestionIds = collect($questionModels)->where('type', 'multiple_choice');
        $totalMc       = $mcQuestionIds->count(); // 5

        foreach ($participants as $idx => $participant) {

            // ── Kelompok HADIR (0-17) ─────────────────────────────────────
            if ($idx < $hadirCount) {
                $participant->update([
                    'attendance_status' => 'hadir',
                    'attended_at'       => now()->subMinutes(rand(5, 120)),
                ]);

                // 15 dari yang hadir sudah kerjakan kuis
                if ($idx < 15) {
                    $correctCount = rand(2, 5);
                    $mcScore      = round(($correctCount / $totalMc) * 100, 2);
                    $isForced     = ($idx === 14); // 1 orang auto-submit
                    $tabSwitch    = $isForced ? rand(4, 6) : rand(0, 2);

                    // Jawaban dummy
                    $answersDetail = [];
                    foreach ($questionModels as $q) {
                        if ($q->type === 'essay') {
                            $answersDetail[$q->id] = [
                                'type'        => 'essay',
                                'user_answer' => 'Hazard identification adalah proses mengenali bahaya yang dapat menyebabkan kecelakaan di lingkungan kerja, penting agar dapat dilakukan pencegahan.',
                                'is_correct'  => null,
                            ];
                        } else {
                            $options    = ['a', 'b', 'c', 'd'];
                            $userAnswer = $options[array_rand($options)];
                            $answersDetail[$q->id] = [
                                'type'           => 'multiple_choice',
                                'user_answer'    => $userAnswer,
                                'correct_answer' => $q->correct_answer,
                                'is_correct'     => $userAnswer === $q->correct_answer,
                            ];
                        }
                    }

                    $hasEssay    = true;
                    $essayStatus = ($idx < 8) ? 'graded' : 'pending'; // 8 sudah dinilai, 7 belum

                    $result = TrainingQuizResult::create([
                        'training_id'             => $training->id,
                        'user_id'                 => $participant->user_id,
                        'training_participant_id'  => $participant->id,
                        'total_questions'         => count($questionModels),
                        'correct_answers'         => $correctCount,
                        'score'                   => $mcScore,
                        'mc_score'                => $mcScore,
                        'essay_score'             => $essayStatus === 'graded' ? rand(65, 100) : null,
                        'essay_status'            => $essayStatus,
                        'essay_feedback'          => $essayStatus === 'graded' ? 'Jawaban cukup baik dan komprehensif.' : null,
                        'tab_switch_count'        => $tabSwitch,
                        'is_force_submitted'      => $isForced,
                        'violation_logs'          => $isForced ? [['type' => 'tab_switch', 'count' => $tabSwitch, 'time' => now()->subMinutes(5)]] : [],
                        'answers'                 => $answersDetail,
                        'submitted_at'            => now()->subMinutes(rand(1, 90)),
                        'reviewed_at'             => $essayStatus === 'graded' ? now()->subMinutes(rand(1, 30)) : null,
                    ]);

                    // Update nilai akhir untuk yang sudah dinilai essay
                    if ($essayStatus === 'graded') {
                        $mcWeight    = 5 / 6;
                        $essayWeight = 1 / 6;
                        $finalScore  = round(($mcScore * $mcWeight) + ($result->essay_score * $essayWeight), 2);
                        $result->update(['score' => $finalScore]);
                    }
                }
            }

            // ── Kelompok TIDAK HADIR (18-22) ──────────────────────────────
            elseif ($idx < $hadirCount + $tidakHadirCount) {
                $participant->update([
                    'attendance_status' => 'tidak_hadir',
                    'attended_at'       => now(),
                    'attendance_notes'  => collect(['Sakit', 'Izin keluarga', 'Dinas luar kota', 'Keperluan mendadak', 'Cuti'])->random(),
                ]);
            }

            // ── Sisanya PENDING (23-29): belum absen sama sekali ──────────
            // tidak perlu update, default sudah pending
        }

        $this->command->newLine();
        $this->command->info('✅ Selesai! Data dummy berhasil dibuat:');
        $this->command->table(
            ['Item', 'Jumlah'],
            [
                ['User peserta dummy', count($users)],
                ['Training',           1],
                ['Total peserta',      count($participants)],
                ['Hadir',              $hadirCount],
                ['Tidak Hadir',        $tidakHadirCount],
                ['Belum Absen',        count($participants) - $hadirCount - $tidakHadirCount],
                ['Sudah kerjakan kuis', 15],
                ['Essay sudah dinilai', 8],
                ['Essay belum dinilai', 7],
                ['Soal kuis (PG+Essay)', count($questionModels)],
            ]
        );

        $this->command->newLine();
        $this->command->info('🔗 Link portal peserta pertama:');
        $firstToken = $participants[0]->token ?? '-';
        $this->command->line('   /training/portal/' . $firstToken);
        $this->command->newLine();
        $this->command->info('📋 Halaman admin training:');
        $this->command->line('   /training/' . $training->id);
    }
}
