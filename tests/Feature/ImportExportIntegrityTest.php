<?php

namespace Tests\Feature;

use App\Exports\TrainingQuestionsExport;
use App\Exports\TrainingQuestionTemplate;
use App\Exports\TrainingRekapWorkbook;
use App\Exports\UserExport;
use App\Imports\FirstSheetOnly;
use App\Imports\LearningModuleQuestionImport;
use App\Imports\TrainingQuestionImport;
use App\Imports\UserImport;
use App\Models\LearningModule;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

/**
 * Import dan export tidak boleh menebak atau mengubah arti data: soal dengan kunci yang tidak jelas ditolak, kolom
 * dibaca dari judulnya, teks (No. HP, ID karyawan) tidak berubah menjadi angka, dan baris user yang bermakna ganda
 * dilaporkan alih-alih menimpa orang lain.
 */
class ImportExportIntegrityTest extends TestCase
{
    private const QUESTION_HEADER = 'tipe_soal,pertanyaan,kunci_jawaban,pilihan_a,pilihan_b,pilihan_c,pilihan_d,pilihan_e,pilihan_f,penjelasan,dipakai_di';

    private User $admin;

    private Training $training;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
        $this->training = Training::create([
            'title' => 'K3 Dasar', 'trainer_id' => $this->admin->id, 'training_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00', 'has_pretest' => true,
        ]);
    }

    /**
     * Import soal pelatihan dari isi CSV; mengembalikan pesan kegagalan (null bila berhasil). Seperti job aslinya,
     * seluruh file berada dalam satu transaksi.
     */
    private function importQuestions(string $csv): ?string
    {
        Storage::disk('local')->put('imports/soal.csv', self::rectangular($csv));

        try {
            DB::transaction(fn () => Excel::import(new FirstSheetOnly(new TrainingQuestionImport($this->training->id)), 'imports/soal.csv', 'local'));
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Samakan jumlah kolom tiap baris dengan baris judulnya, seperti CSV yang disimpan Excel. Tanpa ini pembaca CSV
     * tidak bisa memastikan pemisah kolomnya.
     */
    private static function rectangular(string $csv): string
    {
        $lines = explode("\n", rtrim($csv, "\n"));
        $width = substr_count($lines[0], ',');

        return implode("\n", array_map(fn (string $line) => $line.str_repeat(',', max(0, $width - substr_count($line, ','))), $lines))."\n";
    }

    /**
     * @return array{0: list<string>, 1: int} pesan kesalahan dan jumlah baris berhasil
     */
    private function importUsers(string $csv): array
    {
        Storage::disk('local')->put('imports/users.csv', $csv);
        $import = new UserImport;
        Excel::import($import, 'imports/users.csv', 'local');
        $import->resolvePendingApprovals();

        return [$import->errors(), $import->successCount()];
    }

    public function test_question_columns_are_read_by_their_heading_not_their_position(): void
    {
        // Kolom diacak dan ada kolom tambahan yang tidak dikenal.
        $error = $this->importQuestions(
            "pilihan_b,catatan,kunci_jawaban,pertanyaan,pilihan_a,tipe_soal\n"
            ."Topi,abaikan,A,APD utama?,Helm,PG\n"
        );

        $this->assertNull($error);
        $question = $this->training->questions()->sole();
        $this->assertSame(['APD utama?', 'Helm', 'Topi', 'a'], [$question->question, $question->option_a, $question->option_b, $question->correct_answer]);
    }

    public function test_file_without_the_template_headings_is_rejected(): void
    {
        // Baris pertama langsung berisi data: dulu terbaca sebagai judul dan sisanya ditebak dari urutan kolom.
        $error = $this->importQuestions("PG,APD utama?,A,Helm,Topi\nPG,Warna jalur evakuasi?,B,Merah,Hijau\n");

        $this->assertStringContainsString('Baris pertama file harus berisi judul kolom seperti di template', $error);
        $this->assertStringContainsString('tipe_soal, pertanyaan, kunci_jawaban, pilihan_a, pilihan_b', $error);
        $this->assertSame(0, $this->training->questions()->count());

        $this->assertStringContainsString('muncul dua kali', $this->importQuestions("tipe_soal,pertanyaan,pertanyaan,kunci_jawaban,pilihan_a,pilihan_b\nPG,X,Y,A,1,2\n"));
        $this->assertStringContainsString('File tidak berisi soal', $this->importQuestions(self::QUESTION_HEADER."\n"));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ambiguousQuestionRows(): array
    {
        return [
            'tipe tidak dikenal' => ['BS,Benar atau salah?,A,Benar,Salah', 'tipe_soal "BS" tidak dikenal'],
            'tipe kosong tanpa pilihan' => [',Jelaskan alur evakuasi,,,', 'tipe_soal kosong dan tidak ada pilihan jawaban'],
            'essay dengan pilihan' => ['ESSAY,Jelaskan,,Helm,Topi', 'soal ESSAY tidak boleh punya pilihan jawaban'],
            'pertanyaan kosong' => ['PG,,A,Helm,Topi', 'kolom pertanyaan kosong padahal kolom lain terisi'],
            'kunci kosong' => ['PG,APD utama?,,Helm,Topi', 'kunci_jawaban kosong'],
            'kunci menunjuk pilihan kosong' => ['PG,APD utama?,E,Helm,Topi', 'kunci_jawaban "E" menunjuk Pilihan E yang kosong'],
            'kunci bukan huruf atau teks pilihan' => ['PG,APD utama?,QUESTION,Helm,Topi', 'kunci_jawaban "QUESTION" bukan huruf pilihan (A-F)'],
            'pilihan b kosong' => ['PG,APD utama?,A,Helm,', 'Pilihan B kosong'],
            'pilihan kembar' => ['PG,APD utama?,A,Helm,Topi,helm', 'Pilihan A dan C berisi teks yang sama'],
            'dipakai_di tidak dikenal' => ['PG,APD utama?,A,Helm,Topi,,,,,,TENGAH', 'dipakai_di "TENGAH" tidak dikenal'],
        ];
    }

    #[DataProvider('ambiguousQuestionRows')]
    public function test_an_ambiguous_question_row_cancels_the_whole_file(string $row, string $expected): void
    {
        $error = $this->importQuestions(self::QUESTION_HEADER."\nPG,Soal sehat?,A,Ya,Tidak\n{$row}\n");

        $this->assertStringContainsString('Baris ke-3', $error);
        $this->assertStringContainsString($expected, $error);
        // Baris sehat di atasnya ikut dibatalkan.
        $this->assertSame(0, $this->training->questions()->count());
    }

    public function test_question_values_are_kept_as_written(): void
    {
        $error = $this->importQuestions(self::QUESTION_HEADER."\n"
            // Pilihan berupa angka 0 dan TRUE/FALSE tidak dianggap kosong; kunci huruf kecil.
            ."PG,Berapa 5 - 5?,a,0,5\n"
            ."pg,Bumi itu bulat?,B,FALSE,TRUE\n"
            // Kunci berupa teks jawaban; tipe kosong tetapi punya pilihan; dipakai_di dalam beberapa tulisan.
            .",Ibu kota Indonesia?,jakarta,Bandung,Jakarta,,,,,Sejak 1945,pretest saja\n"
            ."PG,Warna rambu larangan?,A,Merah,Hijau,,,,,,KEDUANYA\n"
            ."Essay,Jelaskan alur evakuasi,Ikuti jalur hijau,,,,,,,,\n"
            ."ESSAY,Sebutkan APD,,,,,,,,,Pretest & Posttest\n"
        );

        $this->assertNull($error);
        $questions = $this->training->questions()->orderBy('id')->get();
        $this->assertSame(['0', '5', 'a'], [$questions[0]->option_a, $questions[0]->option_b, $questions[0]->correct_answer]);
        $this->assertSame(['FALSE', 'TRUE', 'b'], [$questions[1]->option_a, $questions[1]->option_b, $questions[1]->correct_answer]);
        $this->assertSame(['b', 'Sejak 1945', 'pretest'], [$questions[2]->correct_answer, $questions[2]->explanation, $questions[2]->used_in]);
        $this->assertSame('both', $questions[3]->used_in);
        // Essay tanpa dipakai_di hanya di posttest; yang diisi mengikuti isinya.
        $this->assertSame(['essay', 'Ikuti jalur hijau', 'posttest'], [$questions[4]->type, $questions[4]->correct_answer, $questions[4]->used_in]);
        $this->assertSame(['essay', null, 'both'], [$questions[5]->type, $questions[5]->correct_answer, $questions[5]->used_in]);
        $this->assertTrue($questions->every->hasValidKey());
    }

    public function test_template_and_export_round_trip_without_losing_anything(): void
    {
        Storage::disk('local')->put('imports/template.xlsx', Excel::raw(new TrainingQuestionTemplate, ExcelFormat::XLSX));
        Excel::import(new FirstSheetOnly(new TrainingQuestionImport($this->training->id)), 'imports/template.xlsx', 'local');
        $this->assertSame(['both', 'pretest', 'both', 'posttest'], $this->training->questions()->orderBy('id')->pluck('used_in')->all());

        // Teks yang mirip angka atau rumus harus kembali persis sama.
        $this->training->questions()->create([
            'type' => 'multiple_choice', 'question' => '=1+1 hasilnya?', 'option_a' => '0012', 'option_b' => '25.10', 'option_c' => '+62',
            'correct_answer' => 'b', 'explanation' => '10%', 'used_in' => 'posttest',
        ]);
        $columns = ['type', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f', 'correct_answer', 'explanation', 'used_in'];
        $original = $this->training->questions()->orderBy('id')->get($columns)->toArray();

        Storage::disk('local')->put('imports/export.xlsx', Excel::raw(new TrainingQuestionsExport($this->training), ExcelFormat::XLSX));
        $target = Training::create([...$this->training->only(['trainer_id', 'start_time', 'end_time']), 'title' => 'Tujuan', 'training_date' => now()->toDateString()]);
        Excel::import(new FirstSheetOnly(new TrainingQuestionImport($target->id)), 'imports/export.xlsx', 'local');

        $this->assertSame($original, $target->questions()->orderBy('id')->get($columns)->toArray());
    }

    public function test_only_the_first_sheet_of_a_workbook_is_imported(): void
    {
        $workbook = new class implements Export, WithMultipleSheets
        {
            public function sheets(): array
            {
                $notes = new class implements FromArray
                {
                    public function array(): array
                    {
                        return [['Petunjuk'], ['Isi sheet pertama saja'], ['PG', 'Bukan soal', 'A', 'x', 'y']];
                    }
                };

                return [new TrainingQuestionTemplate, $notes];
            }
        };
        Storage::disk('local')->put('imports/dua-sheet.xlsx', Excel::raw($workbook, ExcelFormat::XLSX));

        Excel::import(new FirstSheetOnly(new TrainingQuestionImport($this->training->id)), 'imports/dua-sheet.xlsx', 'local');

        $this->assertSame(4, $this->training->questions()->count());
        $this->assertSame(0, $this->training->questions()->where('question', 'Bukan soal')->count());
    }

    public function test_module_question_import_follows_the_same_rules(): void
    {
        $module = LearningModule::create(['title' => 'Modul', 'passing_score' => 70]);
        $import = function (string $csv) use ($module): ?string {
            Storage::disk('local')->put('imports/modul.csv', self::rectangular($csv));
            try {
                DB::transaction(fn () => Excel::import(new FirstSheetOnly(new LearningModuleQuestionImport($module, 1)), 'imports/modul.csv', 'local'));
            } catch (Throwable $e) {
                return $e->getMessage();
            }

            return null;
        };
        $header = 'pertanyaan,kunci_jawaban,pilihan_a,pilihan_b,pilihan_c,pilihan_d,pilihan_e,penjelasan';

        $this->assertStringContainsString('Kolom yang tidak ditemukan: pertanyaan, kunci_jawaban, pilihan_a, pilihan_b', $import("APD utama?,A,Helm,Topi\n"));
        $this->assertStringContainsString('kunci_jawaban "QUESTION" bukan huruf pilihan (A-E)', $import($header."\nAPD utama?,QUESTION,Helm,Topi\n"));
        $this->assertStringContainsString('kunci_jawaban "D" menunjuk Pilihan D yang kosong', $import($header."\nAPD utama?,D,Helm,Topi\n"));
        $this->assertStringContainsString('Pilihan A dan B berisi teks yang sama', $import($header."\nAPD utama?,A,Helm,Helm\n"));
        $this->assertSame(0, $module->questions()->count());

        $this->assertNull($import($header."\nBerapa 5 - 5?,B,5,0\nAPD utama?,helm,Helm,Topi,,,,Wajib\n"));
        $this->assertSame([['5', '0', 'b'], ['Helm', 'Topi', 'a']], $module->questions()->get()->map(fn ($q) => [$q->option_a, $q->option_b, $q->correct_answer])->all());
    }

    public function test_pasted_text_sample_is_read_exactly(): void
    {
        $text = "1. Apa fungsi dari modul approval dokumen?\nA. Mengesahkan dokumen agar sah digunakan\nB. Menghapus dokumen lama dari server\n"
            ."C. Mengarsipkan dokumen tanpa persetujuan\nD. Mengubah tipe dokumen secara acak\nKUNCI: A\n"
            ."PENJELASAN: Modul approval memastikan dokumen divalidasi oleh atasan sebelum diedarkan.\n\n"
            ."2. Apakah kehadiran pada sesi Zoom wajib dikonfirmasi sebelum kuis?\nA. Ya, wajib hadir\nB. Tidak wajib\nKUNCI: A\n\n"
            ."3. Mana saja departemen pendukung operasional kantor?\nA. Marketing\nB. Sales\nC. HRD\nD. Finance\nE. Legal\nF. General Affair\nKUNCI: C\n\n"
            ."4. Jelaskan secara ringkas materi utama yang telah dipaparkan pada sesi Zoom tadi!\nTIPE: ESSAY\n"
            .'KUNCI: Pemaparan mengenai alur kerja sistem, hak akses user, dan verifikasi absensi.';

        $this->actingAs($this->admin)->post(route('training.questions.import-text', $this->training), ['raw_text' => $text])
            ->assertSessionHas('success', 'Berhasil menambahkan 4 soal kuis dari teks copy-paste!');

        $questions = $this->training->questions()->orderBy('id')->get();
        $this->assertSame(['a', 'a', 'c'], $questions->take(3)->pluck('correct_answer')->all());
        $this->assertSame('Apa fungsi dari modul approval dokumen?', $questions[0]->question);
        $this->assertSame('Modul approval memastikan dokumen divalidasi oleh atasan sebelum diedarkan.', $questions[0]->explanation);
        $this->assertSame('General Affair', $questions[2]->option_f);
        $this->assertSame(['essay', 'Pemaparan mengenai alur kerja sistem, hak akses user, dan verifikasi absensi.'], [$questions[3]->type, $questions[3]->correct_answer]);
    }

    public function test_pasted_text_handles_common_layouts(): void
    {
        $text = "Soal 1: Siapa yang melakukan setting unit?\n\n"   // baris kosong antara pertanyaan dan pilihan
            ."a) Kasir\nb) Gadget Specialist\nc) Tim Finance\n\n"
            ."Jawaban: (b)\n"
            ."Pembahasan: Gadget Specialist mengarahkan customer\n"
            ."dan melakukan setting unit.\n\n"                      // lanjutan penjelasan di baris berikutnya
            ."2) Dokumen pencatatan customer?\nA. Buku Kas\nB. Buku Customer\nKunci jawaban: B. Buku Customer\n\n"
            ."3. Metode pembayaran yang dicek lewat email?\nA. Tunai\nB. Debit\nKunci: debit\n\n"
            ."Jelaskan tugas kasir saat penyetrukan.\n\n"           // essay tanpa nomor, dipisah baris kosong
            ."Sebutkan dua dokumen terkait.\nKunci: Buku Customer dan struk.";

        $this->actingAs($this->admin)->post(route('training.questions.import-text', $this->training), ['raw_text' => $text])
            ->assertSessionHas('success', 'Berhasil menambahkan 5 soal kuis dari teks copy-paste!');

        $questions = $this->training->questions()->orderBy('id')->get();
        $this->assertSame(['Siapa yang melakukan setting unit?', 'multiple_choice', 'b', 'Tim Finance'], [$questions[0]->question, $questions[0]->type, $questions[0]->correct_answer, $questions[0]->option_c]);
        $this->assertSame('Gadget Specialist mengarahkan customer dan melakukan setting unit.', $questions[0]->explanation);
        $this->assertSame(['b', 'b'], [$questions[1]->correct_answer, $questions[2]->correct_answer]);
        $this->assertSame(['essay', 'Jelaskan tugas kasir saat penyetrukan.', null], [$questions[3]->type, $questions[3]->question, $questions[3]->correct_answer]);
        $this->assertSame(['essay', 'Buku Customer dan struk.'], [$questions[4]->type, $questions[4]->correct_answer]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ambiguousPastedQuestions(): array
    {
        return [
            'tanpa kunci' => ["2. APD utama?\nA. Helm\nB. Topi", 'Soal ke-2: kunci jawaban tidak ditemukan'],
            'kunci menunjuk pilihan yang tidak ada' => ["2. APD utama?\nA. Helm\nB. Topi\nKunci: D", 'kunci "D" menunjuk Pilihan D yang tidak ada'],
            'kunci bukan pilihan' => ["2. APD utama?\nA. Helm\nB. Topi\nKunci: Sepatu", 'kunci "Sepatu" bukan huruf pilihan'],
            'huruf dan teks kunci tidak cocok' => ["2. APD utama?\nA. Helm\nB. Topi\nKunci: A. Topi", 'menyebut huruf A tetapi teksnya berbeda dengan Pilihan A'],
            'hanya pilihan a' => ["2. APD utama?\nA. Helm\nKunci: A", 'Pilihan B tidak ada'],
            'huruf pilihan ganda' => ["2. APD utama?\nA. Helm\nA. Topi\nB. Sandal\nKunci: A", 'Pilihan A ditulis dua kali'],
            'pilihan kembar' => ["2. APD utama?\nA. Helm\nB. helm\nKunci: A", 'Pilihan A dan B berisi teks yang sama'],
            'pilihan kosong' => ["2. APD utama?\nA. Helm\nB.\nKunci: A", 'Pilihan B kosong'],
            'pilihan tanpa pertanyaan' => ["2.\nA. Helm\nB. Topi\nKunci: A", 'ada pilihan atau kunci jawaban tanpa pertanyaan'],
            'essay dengan pilihan' => ["2. Jelaskan\nTipe: Essay\nA. Helm\nB. Topi", 'ditandai ESSAY tetapi punya pilihan jawaban'],
            'tipe tidak dikenal' => ["2. Benar atau salah?\nTipe: BS\nA. Benar\nB. Salah\nKunci: A", 'tipe "BS" tidak dikenal'],
        ];
    }

    #[DataProvider('ambiguousPastedQuestions')]
    public function test_an_ambiguous_pasted_question_saves_nothing(string $second, string $expected): void
    {
        $text = "1. Soal sehat?\nA. Ya\nB. Tidak\nKunci: A\n\n".$second;

        $response = $this->actingAs($this->admin)->post(route('training.questions.import-text', $this->training), ['raw_text' => $text]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString($expected, session('error'));
        $this->assertStringContainsString('Tidak ada soal yang disimpan.', session('error'));
        // Soal pertama yang sehat tidak ikut tersimpan, dan teksnya dikembalikan untuk diperbaiki.
        $this->assertSame(0, $this->training->questions()->count());
        $response->assertSessionHasInput('raw_text', $text);
    }

    public function test_exports_keep_phone_numbers_and_ids_as_text(): void
    {
        $staff = User::factory()->staff()->create(['nama_lengkap' => '=BUDI', 'employee_id' => '25.10', 'no_hp' => '081234567890']);
        TrainingParticipant::create(['training_id' => $this->training->id, 'user_id' => $staff->id, 'token' => 'tok', 'attendance_status' => 'hadir', 'attended_at' => now()]);
        $this->training->load(['trainer', 'evaluationQuestions', 'participants.user.divisi', 'participants.quizResult', 'participants.pretestResult', 'participants.evaluation']);

        $path = Storage::disk('local')->path('rekap.xlsx');
        Storage::disk('local')->put('rekap.xlsx', Excel::raw(new TrainingRekapWorkbook($this->training), ExcelFormat::XLSX));
        $sheet = IOFactory::load($path)->getSheet(0);

        $row = collect(range(1, 12))->first(fn (int $r) => $sheet->getCell("C{$r}")->getValue() === '=BUDI');
        $this->assertNotNull($row, 'Baris peserta tidak ditemukan di rekap.');
        // ID "25.10" tidak menjadi 25.1, No. HP tidak kehilangan nol depannya, dan "=BUDI" tidak menjadi rumus.
        $this->assertSame(['25.10', DataType::TYPE_STRING], [$sheet->getCell("B{$row}")->getValue(), $sheet->getCell("B{$row}")->getDataType()]);
        $this->assertSame(['081234567890', DataType::TYPE_STRING], [$sheet->getCell("E{$row}")->getValue(), $sheet->getCell("E{$row}")->getDataType()]);
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell("C{$row}")->getDataType());
        // Belum mengerjakan ditulis "-", bukan 0.
        $this->assertSame('-', $sheet->getCell("M{$row}")->getValue());

        // Pengikat nilai khusus tidak tertinggal untuk export/import berikutnya di proses yang sama.
        $this->assertInstanceOf(DefaultValueBinder::class, Cell::getValueBinder());
        $this->assertStringNotContainsString('App\\Exports', Cell::getValueBinder()::class);
    }

    public function test_user_export_can_be_imported_back_unchanged(): void
    {
        $place = ['area_id' => 1, 'divisi_id' => 1];
        $boss = User::factory()->staff()->create([...$place, 'nama_lengkap' => 'SITI ATASAN', 'username' => 'siti.atasan', 'employee_id' => '25.01.001', 'no_hp' => '081111111111']);
        $staff = User::factory()->staff()->create([...$place, 'nama_lengkap' => 'BUDI STAF', 'username' => 'budistaf', 'employee_id' => '25.10', 'no_hp' => '081234567890', 'approval_id' => $boss->id]);
        User::where('username', 'admin')->update($place);
        $before = User::orderBy('id')->get(['id', 'nama_lengkap', 'username', 'employee_id', 'no_hp', 'approval_id', 'area_id', 'divisi_id'])->toArray();

        Storage::disk('local')->put('imports/users.xlsx', Excel::raw(new UserExport, ExcelFormat::XLSX));
        $import = new UserImport;
        Excel::import($import, 'imports/users.xlsx', 'local');
        $import->resolvePendingApprovals();

        $this->assertSame([], $import->errors());
        $this->assertSame(User::count(), $import->successCount());
        $this->assertSame($before, User::orderBy('id')->get(['id', 'nama_lengkap', 'username', 'employee_id', 'no_hp', 'approval_id', 'area_id', 'divisi_id'])->toArray());
        $this->assertSame('25.10', $staff->fresh()->employee_id);
    }

    public function test_user_import_never_merges_two_different_people(): void
    {
        $budi = User::factory()->staff()->create(['nama_lengkap' => 'BUDI SANTOSO', 'username' => 'budisantoso', 'employee_id' => '25.01.001', 'no_hp' => '081111111111']);
        $siti = User::factory()->staff()->create(['nama_lengkap' => 'SITI AMINAH', 'username' => 'sitiaminah', 'employee_id' => '25.01.002']);
        $header = "nama_lengkap,username,id_karyawan,role,area,divisi\n";

        [$errors, $success] = $this->importUsers($header
            // Orang lain bernama sama, ID berbeda, tanpa username: dulu menimpa Budi yang lama.
            ."Budi Santoso,,25.09.050,STAFF,TEST AREA,Test Division\n"
            // Username Budi dengan ID orang lain.
            ."Budi Baru,budisantoso,25.09.051,STAFF,TEST AREA,Test Division\n"
            // ID milik Budi, username milik Siti.
            ."Budi Santoso,sitiaminah,25.01.001,STAFF,TEST AREA,Test Division\n"
            // ID milik Siti, username diganti: username tidak diubah lewat import.
            ."Siti Aminah,sitibaru,25.01.002,STAFF,TEST AREA,Test Division\n"
            // Baris sehat, lalu user yang sama sekali lagi.
            ."Siti Aminah,sitiaminah,25.01.002,STAFF,TEST AREA,Test Division\n"
            ."Siti Aminah,,25.01.002,STAFF,TEST AREA,Test Division\n"
        );

        $this->assertSame(1, $success);
        $this->assertCount(5, $errors);
        $this->assertStringContainsString('Baris 2: Nama ini menghasilkan username "budisantoso" yang sudah dipakai user lain (ID karyawan 25.01.001)', $errors[0]);
        $this->assertStringContainsString('Baris 3: Username "budisantoso" sudah dipakai user dengan ID karyawan 25.01.001', $errors[1]);
        $this->assertStringContainsString('Baris 4: ID karyawan menunjuk BUDI SANTOSO, tetapi username "sitiaminah" milik SITI AMINAH', $errors[2]);
        $this->assertStringContainsString('Baris 5: Username di file ("sitibaru") berbeda dengan username user ini ("sitiaminah")', $errors[3]);
        $this->assertStringContainsString('Baris 7: User ini sudah diimpor di baris 6 file yang sama', $errors[4]);

        // Tidak ada data Budi atau Siti yang berubah, dan tidak ada user baru.
        $this->assertSame(['BUDI SANTOSO', 'budisantoso', '25.01.001', '081111111111'], array_values($budi->fresh()->only(['nama_lengkap', 'username', 'employee_id', 'no_hp'])));
        $this->assertSame('sitiaminah', $siti->fresh()->username);
        $this->assertSame(0, User::whereIn('employee_id', ['25.09.050', '25.09.051'])->count());
    }

    public function test_user_import_reports_an_approval_it_cannot_be_sure_of(): void
    {
        User::factory()->staff()->create(['nama_lengkap' => 'RINA KEMBAR', 'employee_id' => '25.03.001']);
        User::factory()->staff()->create(['nama_lengkap' => 'RINA KEMBAR', 'employee_id' => '25.03.002']);
        $header = "nama_lengkap,username,id_karyawan,role,area,divisi,approval\n";

        [$errors, $success] = $this->importUsers($header
            // Atasannya baru dibuat di baris berikutnya: tetap terpasang.
            ."Andi Staf,andistaf,25.04.001,STAFF,TEST AREA,Test Division,Dewi Kepala\n"
            ."Dewi Kepala,dewikepala,25.04.002,STAFF,TEST AREA,Test Division,\n"
            // Tidak ada, nama kembar, dan dirinya sendiri.
            ."Tono Staf,tonostaf,25.04.003,STAFF,TEST AREA,Test Division,Tidak Ada\n"
            ."Wati Staf,watistaf,25.04.004,STAFF,TEST AREA,Test Division,Rina Kembar\n"
            ."Eko Sendiri,ekosendiri,25.04.005,STAFF,TEST AREA,Test Division,25.04.005\n"
            // Nama kembar bisa ditunjuk lewat ID karyawannya; "-" berarti kosong.
            ."Yuni Staf,yunistaf,25.04.006,STAFF,TEST AREA,Test Division,25.03.002\n"
            ."Zaki Staf,zakistaf,25.04.007,STAFF,TEST AREA,Test Division,-\n"
        );

        $user = fn (string $id) => User::where('employee_id', $id)->sole();
        $this->assertSame($user('25.04.002')->id, $user('25.04.001')->approval_id);
        $this->assertSame($user('25.03.002')->id, $user('25.04.006')->approval_id);
        $this->assertNull($user('25.04.007')->approval_id);

        $this->assertCount(3, $errors);
        $messages = implode("\n", $errors);
        $this->assertStringContainsString('Baris 5: Approval "Rina Kembar" cocok dengan lebih dari satu user', $messages);
        $this->assertStringContainsString('Baris 4: Approval "Tidak Ada" tidak ditemukan', $messages);
        $this->assertStringContainsString('Baris 6: Approval "25.04.005" membentuk siklus', $messages);
        // Baris dengan approval yang tidak ditemukan tetap membuat usernya, tanpa approval.
        $this->assertNull($user('25.04.003')->approval_id);
        $this->assertNull($user('25.04.005')->approval_id);
        $this->assertSame(0, User::where('employee_id', '25.04.004')->count());
        $this->assertSame(6, $success);
    }
}
