<?php

namespace Tests\Feature;

use App\Exports\LearningModuleRekapExport;
use App\Models\Document;
use App\Models\LearningModule;
use App\Models\LearningModuleAttempt;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class LearningModuleManagementTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
    }

    private function createModuleWithMaterials(): LearningModule
    {
        $video = Video::create(['title' => 'Video K3', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $document = Document::create(['name' => 'SOP K3', 'divisi_id' => 1, 'document_type' => 1, 'path' => 'k3.pdf']);

        $this->actingAs($this->admin)->post(route('learning-module.store'), [
            'title' => 'K3 Dasar',
            'passing_score' => 75,
            'shuffle_questions' => '1',
            'divisi_ids' => [1],
            'items' => ['document:'.$document->id, 'video:'.$video->id],
        ])->assertSessionHasNoErrors();

        return LearningModule::sole();
    }

    public function test_admin_creates_module_with_ordered_materials_and_targets(): void
    {
        $module = $this->createModuleWithMaterials();

        $this->assertSame('K3 Dasar', $module->title);
        $this->assertSame(75, $module->passing_score);
        $this->assertFalse($module->is_published);
        $this->assertSame([1], $module->divisis()->pluck('divisis.id')->all());
        $this->assertSame(['document', 'video'], $module->items()->pluck('materialable_type')->all());
    }

    public function test_updating_materials_keeps_progress_of_remaining_items(): void
    {
        $module = $this->createModuleWithMaterials();
        [$documentItem, $videoItem] = $module->items()->get()->all();
        $documentItem->progress()->create(['user_id' => $this->admin->id, 'opened_at' => now(), 'completed_at' => now()]);

        $this->actingAs($this->admin)->put(route('learning-module.update', $module), [
            'title' => 'K3 Dasar',
            'passing_score' => 75,
            'items' => [$videoItem->key(), $documentItem->key()],
        ])->assertRedirect(route('learning-module.show', $module));

        $this->assertSame([$videoItem->id, $documentItem->id], $module->items()->pluck('id')->all());
        $this->assertSame(1, $documentItem->progress()->count());
    }

    public function test_module_cannot_be_published_without_questions(): void
    {
        $module = $this->createModuleWithMaterials();

        $this->actingAs($this->admin)->post(route('learning-module.publish', $module))->assertSessionHas('error');
        $this->assertFalse($module->fresh()->is_published);

        $this->actingAs($this->admin)->post(route('learning-module.questions.store', $module), [
            'question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('learning-module.publish', $module))->assertSessionHas('success');
        $this->assertTrue($module->fresh()->is_published);
    }

    public function test_answer_key_must_point_to_a_filled_option(): void
    {
        $module = $this->createModuleWithMaterials();

        $this->actingAs($this->admin)->post(route('learning-module.questions.store', $module), [
            'question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'd',
        ])->assertSessionHasErrors('correct_answer');
    }

    public function test_questions_are_imported_from_excel(): void
    {
        $module = $this->createModuleWithMaterials();
        $path = tempnam(sys_get_temp_dir(), 'soal').'.csv';
        file_put_contents($path, "pertanyaan,kunci_jawaban,pilihan_a,pilihan_b,pilihan_c,pilihan_d,pilihan_e,penjelasan\n"
            ."APD utama?,A,Helm,Topi,,,,\n"
            ."Warna jalur evakuasi?,Hijau,Merah,Hijau,Biru,,,Jalur evakuasi hijau\n");

        $this->actingAs($this->admin)->post(route('learning-module.questions.import-excel', $module), [
            'file' => new UploadedFile($path, 'soal.csv', 'text/csv', null, true),
        ])->assertSessionHas('success');

        $this->assertSame(['a', 'b'], $module->questions()->pluck('correct_answer')->all());
        @unlink($path);
    }

    public function test_results_page_and_export_show_every_attempt(): void
    {
        $module = $this->createModuleWithMaterials();
        $module->questions()->create(['question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a']);
        $staff = User::factory()->staff()->create(['nama_lengkap' => 'BUDI PESERTA']);
        $questionId = $module->questions()->value('id');

        LearningModuleAttempt::record($module, $staff, 'pretest', [$questionId => 'b']);
        LearningModuleAttempt::record($module, $staff, 'posttest', [$questionId => 'b']);
        LearningModuleAttempt::record($module, $staff, 'posttest', [$questionId => 'a']);

        $this->actingAs($this->admin)->get(route('learning-module.show', $module))
            ->assertOk()
            ->assertSee('BUDI PESERTA')
            ->assertSee('+100')
            ->assertSee('Lulus');

        $this->actingAs($this->admin)->get(route('learning-module.participant', [$module, $staff]))
            ->assertOk()
            ->assertSee('Posttest ke-1')
            ->assertSee('Posttest ke-2');

        Excel::fake();
        $this->actingAs($this->admin)->post(route('learning-module.export', $module))->assertSessionHas('info');

        $path = $this->admin->notifications()->first()->data['download_path'];
        $this->assertStringStartsWith('rekap_modul_k3-dasar_', basename($path));
        Excel::assertStored($path, 'local', function (LearningModuleRekapExport $export) {
            [$rekap, $attempts] = $export->sheets();

            return count($attempts->array()) === 3 && $rekap->array()[6][2] === 'BUDI PESERTA';
        });
    }

    public function test_management_pages_render(): void
    {
        $module = $this->createModuleWithMaterials();
        $module->questions()->create(['question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a']);

        $this->actingAs($this->admin)->get(route('learning-module.index'))->assertOk()->assertSee('K3 Dasar');
        $this->actingAs($this->admin)->get(route('learning-module.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('learning-module.edit', $module))->assertOk()->assertSee('Video: Video K3');
        $this->actingAs($this->admin)->get(route('learning-module.questions.index', $module))->assertOk()->assertSee('APD utama?');
        $this->actingAs($this->admin)->get(route('learning-module.questions.template', $module))->assertOk();
    }

    public function test_draft_module_pages_show_setup_steps_and_publish_state(): void
    {
        $module = $this->createModuleWithMaterials();

        // Belum ada soal: checklist tampil, tombol publikasi nonaktif dengan alasannya.
        $this->actingAs($this->admin)->get(route('learning-module.show', $module))
            ->assertOk()
            ->assertSee('Langkah menyiapkan modul')
            ->assertSee('Bisa dipublikasikan setelah ada soal.')
            ->assertSee('Butuh minimal 1 materi dan 1 soal');

        $module->questions()->create(['question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a']);

        $this->actingAs($this->admin)->get(route('learning-module.questions.index', $module))
            ->assertOk()
            ->assertSee('Siap. Modul akan muncul di &quot;Modul Saya&quot; peserta sasaran.', false)
            ->assertDontSee('Butuh minimal 1 materi dan 1 soal');

        // Sudah dipublikasikan: checklist disembunyikan, tombol berganti jadi Tarik Publikasi.
        $module->update(['is_published' => true]);
        $this->actingAs($this->admin)->get(route('learning-module.show', $module))
            ->assertOk()
            ->assertDontSee('Langkah menyiapkan modul')
            ->assertSee('Tarik Publikasi');
    }

    public function test_target_users_follow_the_same_rules_as_module_visibility(): void
    {
        $module = $this->createModuleWithMaterials();
        $module->update(['is_published' => true]);
        $module->load(['divisis', 'roles']);

        $expected = User::all()->filter(fn (User $user) => $module->isVisibleTo($user))->pluck('id')->sort()->values()->all();

        $this->assertSame($expected, $module->targetUsers()->pluck('id')->sort()->values()->all());
    }

    public function test_video_page_renders_for_each_source(): void
    {
        $module = $this->createModuleWithMaterials();
        $module->questions()->create(['question' => 'APD utama?', 'option_a' => 'Helm', 'option_b' => 'Topi', 'correct_answer' => 'a']);
        $module->update(['is_published' => true]);
        $file = Video::create(['title' => 'Video File', 'video_type' => 'file', 'video_file' => 'videos/a.mp4']);
        $drive = Video::create(['title' => 'Video Drive', 'video_type' => 'link', 'video_link' => 'https://drive.google.com/file/d/abc123/view']);
        $module->items()->create(['materialable_type' => 'video', 'materialable_id' => $file->id, 'sort_order' => 5]);
        $module->items()->create(['materialable_type' => 'video', 'materialable_id' => $drive->id, 'sort_order' => 6]);

        $staff = User::factory()->staff()->create();
        LearningModuleAttempt::record($module, $staff, 'pretest', []);

        foreach ($module->items()->where('materialable_type', 'video')->get() as $item) {
            $this->actingAs($staff)->get(route('learning.items.show', [$module, $item]))->assertOk();
        }
        $this->actingAs($staff)->get(route('learning.show', $module))->assertOk()->assertSee('Video Drive');
    }

    public function test_staff_cannot_manage_modules(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('learning-module.index'))->assertRedirect();
        $this->actingAs($staff)->post(route('learning-module.store'), ['title' => 'X', 'passing_score' => 70]);
        $this->assertSame(0, LearningModule::count());
    }

    public function test_trainer_can_manage_modules(): void
    {
        $trainer = User::factory()->staff()->trainer()->create();

        $this->actingAs($trainer)->get(route('learning-module.index'))->assertOk();
    }
}
