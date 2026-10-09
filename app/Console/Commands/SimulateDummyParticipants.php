<?php

namespace App\Console\Commands;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuizResult;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SimulateDummyParticipants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'training:simulate-dummy {training_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create 5 dummy participants and simulate the full training and quiz workflow';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $trainingId = $this->argument('training_id') ?? 2;
        $training = Training::find($trainingId);

        if (! $training) {
            $admin = User::where('username', 'admin')->first();
            $training = Training::create([
                'title' => 'Pelatihan Implementasi Modul Web',
                'trainer_id' => $admin ? $admin->id : 1,
                'description' => 'Pelatihan komprehensif alur kerja sistem modul web dan evaluasi kuis.',
                'training_date' => now()->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'zoom_link' => 'https://us05web.zoom.us/j/84088287809?pwd=demo',
                'status' => 'ongoing',
                'is_quiz_active' => true,
            ]);
            $this->info("Dibuat sesi pelatihan baru dengan ID: {$training->id}");
        } else {
            $training->update([
                'status' => 'ongoing',
                'is_quiz_active' => true,
            ]);
            $this->info("Menggunakan pelatihan ID {$training->id}: {$training->title}");
        }

        // 1. SETUP / UPDATE QUESTIONS (4 Soal: PG A-D, PG A-B, PG A-F, dan Essay)
        $this->info('Menyiapkan bank soal kuis pelatihan...');
        $training->questions()->delete();

        $q1 = TrainingQuestion::create([
            'training_id' => $training->id,
            'type' => 'multiple_choice',
            'question' => 'Apa tujuan utama dari proses approval modul dokumen?',
            'option_a' => 'Memastikan keabsahan dan keakuratan informasi dokumen',
            'option_b' => 'Menghapus dokumen lama secara otomatis',
            'option_c' => 'Mengubah format dokumen ke PDF tanpa persetujuan',
            'option_d' => 'Membatasi hak akses seluruh karyawan',
            'correct_answer' => 'a',
            'explanation' => 'Approval memastikan dokumen divalidasi oleh atasan sebelum diedarkan.',
        ]);

        $q2 = TrainingQuestion::create([
            'training_id' => $training->id,
            'type' => 'multiple_choice',
            'question' => 'Apakah peserta wajib mengisi absensi kehadiran sebelum mengerjakan kuis evaluasi?',
            'option_a' => 'Ya, wajib konfirmasi kehadiran',
            'option_b' => 'Tidak wajib',
            'correct_answer' => 'a',
            'explanation' => 'Hanya peserta yang hadir di sesi Zoom yang berhak mengikuti kuis evaluasi.',
        ]);

        $q3 = TrainingQuestion::create([
            'training_id' => $training->id,
            'type' => 'multiple_choice',
            'question' => 'Mana dari divisi berikut yang termasuk dalam ruang lingkup pelatihan hari ini?',
            'option_a' => 'Marketing',
            'option_b' => 'Sales Direct',
            'option_c' => 'Finance',
            'option_d' => 'Procurement',
            'option_e' => 'Event Organizer (EO)',
            'option_f' => 'Legal & Compliance',
            'correct_answer' => 'e',
            'explanation' => 'Pelatihan ini difokuskan untuk tim Event Organizer (EO).',
        ]);

        $q4 = TrainingQuestion::create([
            'training_id' => $training->id,
            'type' => 'essay',
            'question' => 'Jelaskan secara ringkas pemahaman Anda mengenai alur kerja modul setelah menyimak sesi Zoom!',
            'correct_answer' => 'Peserta memahami alur validasi dokumen, verifikasi kehadiran Zoom, dan kepatuhan SOP perusahaan.',
            'explanation' => 'Penilaian mencakup pemahaman alur kerja dan implementasi praktis.',
        ]);

        $this->info('Berhasil membuat 4 soal (PG A-D, PG A-B True/False, PG A-F, dan Essay).');

        // 2. DATA 5 DUMMY PESERTA
        $dummyUsers = [
            [
                'full_name' => 'Budi Santoso',
                'username' => 'budi.santoso',
                'email' => 'budi.santoso@example.com',
                'id_karyawan' => 'KRY-101',
                'no_wa' => '08123456701',
                'answers' => [
                    $q1->id => 'a', // Benar
                    $q2->id => 'a', // Benar
                    $q3->id => 'e', // Benar
                    $q4->id => 'Menurut materi zoom, modul approval bertujuan memvalidasi setiap dokumen sebelum diedarkan secara resmi ke seluruh divisi.',
                ],
                'note' => 'Hadir tepat waktu jam 09:00',
            ],
            [
                'full_name' => 'Siti Rahmawati',
                'username' => 'siti.rahmawati',
                'email' => 'siti.rahmawati@example.com',
                'id_karyawan' => 'KRY-102',
                'no_wa' => '08123456702',
                'answers' => [
                    $q1->id => 'a', // Benar
                    $q2->id => 'b', // Salah
                    $q3->id => 'e', // Benar
                    $q4->id => 'Fungsi utama adalah memastikan dokumen yang diunggah sudah sesuai format SOP perusahaan dan mudah diarsipkan.',
                ],
                'note' => 'Hadir melalui Zoom mobile',
            ],
            [
                'full_name' => 'Ahmad Fauzi',
                'username' => 'ahmad.fauzi',
                'email' => 'ahmad.fauzi@example.com',
                'id_karyawan' => 'KRY-103',
                'no_wa' => '08123456703',
                'answers' => [
                    $q1->id => 'a', // Benar
                    $q2->id => 'a', // Benar
                    $q3->id => 'e', // Benar
                    $q4->id => 'Memastikan otentikasi dokumen dan memudahkan tracking riwayat approval oleh manajer secara transparan.',
                ],
                'note' => 'Hadir dan aktif bertanya di Zoom',
            ],
            [
                'full_name' => 'Dewi Lestari',
                'username' => 'dewi.lestari',
                'email' => 'dewi.lestari@example.com',
                'id_karyawan' => 'KRY-104',
                'no_wa' => '08123456704',
                'answers' => [
                    $q1->id => 'b', // Salah
                    $q2->id => 'a', // Benar
                    $q3->id => 'a', // Salah
                    $q4->id => 'Untuk mempermudah pengarsipan berkas kerja karyawan agar terdokumentasi dengan baik di sistem cloud.',
                ],
                'note' => 'Hadir',
            ],
            [
                'full_name' => 'Rian Pratama',
                'username' => 'rian.pratama',
                'email' => 'rian.pratama@example.com',
                'id_karyawan' => 'KRY-105',
                'no_wa' => '08123456705',
                'answers' => [
                    $q1->id => 'a', // Benar
                    $q2->id => 'a', // Benar
                    $q3->id => 'e', // Benar
                    $q4->id => 'Sebagai standarisasi alur kerja digital dan memangkas waktu birokrasi verifikasi dokumen secara manual.',
                ],
                'note' => 'Hadir dari awal sesi',
            ],
        ];

        $this->info('Memproses 5 peserta dan simulasi pengerjaan pelatihan...');

        $resultsTable = [];

        foreach ($dummyUsers as $index => $data) {
            $user = User::firstOrCreate(
                ['username' => $data['username']],
                [
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'id_karyawan' => $data['id_karyawan'],
                    'no_wa' => $data['no_wa'],
                    'password' => 'password123',
                    'divisi_id' => 1,
                    'job_level_id' => 2,
                ]
            );

            // Create or get participant token
            $token = Str::random(40).'_'.time().$index;
            $participant = TrainingParticipant::updateOrCreate(
                ['training_id' => $training->id, 'user_id' => $user->id],
                [
                    'token' => $token,
                    'attendance_status' => 'hadir',
                    'attended_at' => Carbon::now()->subMinutes(60 - ($index * 5)),
                    'attendance_notes' => $data['note'],
                    'wa_status' => 'Terkirim',
                    'wa_sent_at' => Carbon::now()->subMinutes(90),
                ]
            );

            // SIMULATE QUIZ SUBMISSION
            $submittedAnswers = $data['answers'];
            $mcTotal = 0;
            $correctCount = 0;
            $answersDetails = [];

            foreach ($training->questions as $q) {
                $userAns = $submittedAnswers[$q->id] ?? null;

                if ($q->type === 'essay') {
                    $answersDetails[$q->id] = [
                        'type' => 'essay',
                        'user_answer' => (string) $userAns,
                        'correct_answer' => $q->correct_answer,
                        'is_correct' => null,
                    ];
                } else {
                    $mcTotal++;
                    $isCorrect = ($userAns && strtolower((string) $userAns) === strtolower((string) $q->correct_answer));
                    if ($isCorrect) {
                        $correctCount++;
                    }

                    $answersDetails[$q->id] = [
                        'type' => 'multiple_choice',
                        'user_answer' => $userAns,
                        'correct_answer' => $q->correct_answer,
                        'is_correct' => $isCorrect,
                    ];
                }
            }

            $mcScore = $mcTotal > 0 ? round(($correctCount / $mcTotal) * 100, 2) : 100;

            // Participants 1, 2, 3 are already graded, 4 and 5 are pending review
            $presetGrades = [
                0 => ['score' => 95, 'feedback' => 'Penjelasan alur kerja sangat komprehensif dan akurat.'],
                1 => ['score' => 85, 'feedback' => 'Konsep SOP sudah dipahami dengan baik.'],
                2 => ['score' => 90, 'feedback' => 'Analisis transparansi otentikasi dokumen sangat tepat.'],
            ];

            $isGraded = isset($presetGrades[$index]);
            $essayScore = $isGraded ? $presetGrades[$index]['score'] : null;
            $essayFeedback = $isGraded ? $presetGrades[$index]['feedback'] : null;
            $essayStatus = $isGraded ? 'graded' : 'pending';

            // Calculate final score proportionally (3 PG = 75%, 1 Essay = 25%)
            if ($isGraded) {
                $finalScore = round(($mcScore * 0.75) + ($essayScore * 0.25), 2);
            } else {
                $finalScore = $mcScore;
            }

            // Clear previous result and record fresh
            TrainingQuizResult::where('training_participant_id', $participant->id)->delete();
            TrainingQuizResult::create([
                'training_id' => $training->id,
                'user_id' => $user->id,
                'training_participant_id' => $participant->id,
                'total_questions' => $training->questions->count(),
                'correct_answers' => $correctCount,
                'score' => $finalScore,
                'mc_score' => $mcScore,
                'essay_score' => $essayScore,
                'essay_status' => $essayStatus,
                'essay_feedback' => $essayFeedback,
                'reviewed_at' => $isGraded ? Carbon::now()->subMinutes(15) : null,
                'answers' => $answersDetails,
                'submitted_at' => Carbon::now()->subMinutes(30 - ($index * 4)),
            ]);

            $resultsTable[] = [
                'No' => $index + 1,
                'Nama' => $user->full_name,
                'Kehadiran' => 'HADIR',
                'Nilai PG' => "{$mcScore} ({$correctCount}/{$mcTotal})",
                'Nilai Essay' => $isGraded ? "{$essayScore} (Dinilai)" : 'Pending Review',
                'Nilai Akhir' => "{$finalScore}",
                'Portal URL' => "/training/portal/{$participant->token}",
            ];
        }

        $this->table(['No', 'Nama Peserta', 'Kehadiran', 'Nilai PG', 'Nilai Essay', 'Nilai Akhir', 'Tautan Akses Portal Peserta'], $resultsTable);
        $this->info('Simulasi 5 peserta selesai dengan sukses!');
        $this->info("Silakan cek halaman Admin: http://127.0.0.1:8000/training/{$training->id}");

        return 0;
    }
}
