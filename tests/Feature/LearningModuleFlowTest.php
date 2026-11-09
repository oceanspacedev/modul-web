<?php

namespace Tests\Feature;

use App\Models\Divisi;
use App\Models\Document;
use App\Models\LearningModule;
use App\Models\LearningModuleAttempt;
use App\Models\LearningModuleItem;
use App\Models\LearningModuleItemProgress;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use Tests\TestCase;

/**
 * Modul Belajar: pretest sekali -> semua materi -> posttest yang bisa diulang dengan riwayat tersimpan.
 */
class LearningModuleFlowTest extends TestCase
{
    private User $staff;

    private LearningModule $module;

    private LearningModuleItem $videoItem;

    private LearningModuleItem $documentItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff()->create();

        $this->module = LearningModule::create([
            'title' => 'SOP Gudang',
            'passing_score' => 70,
            'shuffle_questions' => false,
            'is_published' => true,
        ]);

        $video = Video::create(['title' => 'Video SOP', 'video_type' => 'link', 'video_link' => 'https://youtu.be/dQw4w9WgXcQ']);
        $document = Document::create(['name' => 'SOP PDF', 'divisi_id' => 1, 'document_type' => 1, 'path' => 'sop.pdf']);

        $this->videoItem = $this->module->items()->create(['materialable_type' => 'video', 'materialable_id' => $video->id, 'sort_order' => 0]);
        $this->documentItem = $this->module->items()->create(['materialable_type' => 'document', 'materialable_id' => $document->id, 'sort_order' => 1]);

        $this->module->questions()->create([
            'question' => 'Warna helm wajib?', 'option_a' => 'Kuning', 'option_b' => 'Merah', 'correct_answer' => 'a', 'sort_order' => 1,
        ]);
        $this->module->questions()->create([
            'question' => 'Batas beban rak?', 'option_a' => '100 kg', 'option_b' => '500 kg', 'option_c' => '1 ton', 'correct_answer' => 'c', 'sort_order' => 2,
        ]);
    }

    private function answers(string $first, string $second): array
    {
        $ids = $this->module->questions()->pluck('id');

        return ['answers' => [$ids[0] => $first, $ids[1] => $second]];
    }

    private function completeMaterials(): void
    {
        $this->actingAs($this->staff)->get(route('learning.items.open', [$this->module, $this->documentItem]))->assertRedirect();
        $this->actingAs($this->staff)->postJson(route('learning.items.complete', [$this->module, $this->videoItem]))->assertOk();
    }

    public function test_full_flow_pretest_materials_then_repeatable_posttest(): void
    {
        $this->actingAs($this->staff)->get(route('learning.index'))->assertOk()->assertSee('SOP Gudang');

        // Materi dan posttest terkunci sebelum pretest.
        $this->actingAs($this->staff)->get(route('learning.items.show', [$this->module, $this->videoItem]))
            ->assertRedirect(route('learning.show', $this->module));
        $this->actingAs($this->staff)->get(route('learning.quiz', [$this->module, 'posttest']))
            ->assertRedirect(route('learning.show', $this->module));

        $this->actingAs($this->staff)->get(route('learning.quiz', [$this->module, 'pretest']))->assertOk()->assertSee('Warna helm wajib?');
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'pretest']), $this->answers('a', 'a'))->assertRedirect();

        $pretest = LearningModuleAttempt::where('phase', 'pretest')->sole();
        $this->assertSame(50.0, $pretest->score);
        $this->assertSame(1, $pretest->correct_answers);

        // Materi terbuka, posttest masih terkunci sampai semua materi selesai.
        $this->actingAs($this->staff)->get(route('learning.items.show', [$this->module, $this->videoItem]))->assertOk();
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'posttest']), $this->answers('a', 'c'))
            ->assertRedirect(route('learning.show', $this->module));
        $this->assertSame(0, LearningModuleAttempt::where('phase', 'posttest')->count());

        $this->completeMaterials();

        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'posttest']), $this->answers('b', 'a'))->assertRedirect();
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'posttest']), $this->answers('a', 'c'))->assertRedirect();
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'posttest']), $this->answers('a', 'a'))->assertRedirect();

        $posttests = LearningModuleAttempt::where('phase', 'posttest')->orderBy('attempt_number')->get();
        $this->assertSame([1, 2, 3], $posttests->pluck('attempt_number')->all());
        $this->assertSame([0.0, 100.0, 50.0], $posttests->pluck('score')->all());
        $this->assertSame([false, true, false], $posttests->pluck('is_passed')->all());

        $this->actingAs($this->staff)->get(route('learning.show', $this->module))
            ->assertOk()
            ->assertSee('Posttest ke-3')
            ->assertSee('Ulangi Posttest');
    }

    public function test_pretest_can_only_be_taken_once(): void
    {
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'pretest']), $this->answers('a', 'a'));

        $this->actingAs($this->staff)->get(route('learning.quiz', [$this->module, 'pretest']))
            ->assertRedirect(route('learning.show', $this->module));
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'pretest']), $this->answers('a', 'c'))
            ->assertRedirect(route('learning.show', $this->module));

        $this->assertSame(1, LearningModuleAttempt::where('phase', 'pretest')->count());
        $this->assertSame(50.0, LearningModuleAttempt::where('phase', 'pretest')->sole()->score);
    }

    public function test_attempt_keeps_snapshot_after_questions_change(): void
    {
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'pretest']), $this->answers('a', 'b'));
        $attempt = LearningModuleAttempt::sole();

        $this->module->questions()->delete();

        $this->actingAs($this->staff)->get(route('learning.attempt', [$this->module, $attempt]))
            ->assertOk()
            ->assertSee('Warna helm wajib?')
            ->assertSee('Batas beban rak?');
    }

    public function test_result_page_shows_the_answer_key_only_when_the_phase_allows_it(): void
    {
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'pretest']), $this->answers('a', 'b'));
        $attempt = LearningModuleAttempt::sole();

        $this->actingAs($this->staff)->get(route('learning.attempt', [$this->module, $attempt]))
            ->assertOk()
            ->assertSee('Rincian Jawaban');

        $this->module->update(['pretest_shows_answers' => false]);

        $this->actingAs($this->staff)->get(route('learning.attempt', [$this->module, $attempt]))
            ->assertOk()
            ->assertSee('Rincian jawaban tidak ditampilkan')
            ->assertDontSee('>Kunci<', false);
    }

    public function test_participant_cannot_view_someone_elses_attempt(): void
    {
        $other = User::factory()->staff()->create();
        $this->actingAs($other)->post(route('learning.submit', [$this->module, 'pretest']), $this->answers('a', 'b'));

        $this->actingAs($this->staff)->get(route('learning.attempt', [$this->module, LearningModuleAttempt::sole()]))->assertNotFound();
    }

    public function test_opening_document_records_progress_and_redirects_to_file(): void
    {
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'pretest']), $this->answers('a', 'b'));

        $this->actingAs($this->staff)->get(route('learning.items.open', [$this->module, $this->documentItem]))
            ->assertRedirectContains('dokumen/sop.pdf');

        $progress = LearningModuleItemProgress::sole();
        $this->assertSame($this->documentItem->id, $progress->learning_module_item_id);
        $this->assertNotNull($progress->completed_at);
    }

    public function test_new_material_locks_posttest_again(): void
    {
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'pretest']), $this->answers('a', 'b'));
        $this->completeMaterials();

        $extra = Video::create(['title' => 'Video Tambahan', 'video_type' => 'link', 'video_link' => 'https://youtu.be/abcdefghijk']);
        $this->module->items()->create(['materialable_type' => 'video', 'materialable_id' => $extra->id, 'sort_order' => 2]);

        $this->actingAs($this->staff)->get(route('learning.quiz', [$this->module, 'posttest']))
            ->assertRedirect(route('learning.show', $this->module));
    }

    public function test_deleted_material_is_no_longer_required(): void
    {
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'pretest']), $this->answers('a', 'b'));
        $this->actingAs($this->staff)->postJson(route('learning.items.complete', [$this->module, $this->videoItem]))->assertOk();

        $this->documentItem->materialable->delete();

        $this->actingAs($this->staff)->get(route('learning.quiz', [$this->module, 'posttest']))->assertOk();
    }

    public function test_module_is_only_visible_to_its_target_division_and_job_title(): void
    {
        $otherDivisi = Divisi::create(['name' => 'Divisi Lain', 'area_id' => 1]);
        $this->module->divisis()->sync([$otherDivisi->id]);

        $this->actingAs($this->staff)->get(route('learning.index'))->assertOk()->assertDontSee('SOP Gudang');
        $this->actingAs($this->staff)->get(route('learning.show', $this->module))->assertSessionHas('error');

        $this->module->divisis()->sync([1]);
        $this->module->roles()->sync([Role::findByName('MANAGER', 'web')->id]);
        $this->actingAs($this->staff)->get(route('learning.show', $this->module))->assertSessionHas('error');

        $this->module->roles()->sync([Role::findByName(User::ROLE_STAFF, 'web')->id]);
        $this->actingAs($this->staff)->get(route('learning.show', $this->module))->assertOk();
    }

    public function test_unpublished_module_is_hidden_from_participants(): void
    {
        $this->module->update(['is_published' => false]);

        $this->actingAs($this->staff)->get(route('learning.index'))->assertDontSee('SOP Gudang');
        // 403 di aplikasi ini dialihkan kembali dengan pesan error.
        $this->actingAs($this->staff)->get(route('learning.show', $this->module))->assertSessionHas('error');
        $this->actingAs($this->staff)->post(route('learning.submit', [$this->module, 'pretest']), $this->answers('a', 'a'));
        $this->assertSame(0, LearningModuleAttempt::count());
    }
}
