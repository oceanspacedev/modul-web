<?php

namespace Tests\Feature;

use App\Models\DocumentCategory;
use App\Models\DocumentDraft;
use App\Models\User;
use App\Support\DocumentBuilderTemplates;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Langkah form Dokumen Builder bisa diatur OD Leader per kategori: tambah, ubah, hapus, urutkan, dan kembalikan ke bawaan.
 */
class DocumentBuilderStepTest extends TestCase
{
    private function member(string $access): User
    {
        $user = User::factory()->staff()->create();
        $user->setOdReportAccess($access);

        return $user->fresh();
    }

    private function sop(): DocumentCategory
    {
        return DocumentCategory::where('code', 'SOP')->firstOrFail();
    }

    public function test_only_the_od_leader_can_open_the_step_settings(): void
    {
        $this->actingAs($this->member(User::OD_STAFF))->get(route('document-category.builder-steps.edit', $this->sop()))->assertRedirect();

        $this->actingAs($this->member(User::OD_LEADER))->get(route('document-category.builder-steps.edit', $this->sop()))
            ->assertOk()
            ->assertSee('Judul SOP')
            ->assertSee('Bawaan')
            ->assertSee('Prosedur');
    }

    public function test_leader_adds_edits_removes_and_reorders_steps_and_the_form_follows(): void
    {
        $leader = $this->member(User::OD_LEADER);
        $sop = $this->sop();

        $this->actingAs($leader)->put(route('document-category.builder-steps.update', $sop), ['steps' => [
            'a' => ['key' => 'objective', 'label' => 'Tujuan Prosedur', 'hint' => 'Hasil akhir', 'type' => 'textarea', 'required' => '1'],
            'b' => ['key' => 'title', 'label' => 'Nama SOP', 'type' => 'input'],
            'n1' => ['key' => '', 'label' => 'Lokasi Penerapan', 'placeholder' => 'Contoh: Gudang Cikarang', 'type' => 'input'],
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $questions = DocumentBuilderTemplates::for($sop->fresh())['questions'];
        $this->assertSame(['Tujuan Prosedur', 'Nama SOP', 'Lokasi Penerapan'], array_column($questions, 'label'));
        $this->assertSame([true, true, false], array_column($questions, 'required'), 'judul selalu wajib');
        $this->assertMatchesRegularExpression('/^q_[a-z0-9]{8}$/', $questions[2]['key']);

        $this->actingAs($leader)->get(route('document-builder.form', $sop))
            ->assertOk()
            ->assertSeeInOrder(['Tujuan Prosedur', 'Nama SOP', 'Lokasi Penerapan'])
            ->assertSee('Contoh: Gudang Cikarang')
            ->assertDontSee('Ruang Lingkup</span>', false);

        $this->actingAs($leader)->get(route('document-category.builder-steps.edit', $sop))->assertOk()->assertSee('Sudah diubah');
    }

    public function test_the_title_step_cannot_be_removed(): void
    {
        $this->actingAs($this->member(User::OD_LEADER))->put(route('document-category.builder-steps.update', $this->sop()), ['steps' => [
            ['key' => 'objective', 'label' => 'Tujuan', 'type' => 'textarea'],
        ]])->assertSessionHasErrors('steps');

        $this->assertFalse(DocumentBuilderTemplates::for($this->sop()->fresh())['customized']);
    }

    public function test_reset_returns_to_the_default_steps(): void
    {
        $leader = $this->member(User::OD_LEADER);
        $sop = $this->sop();
        $sop->builderQuestions()->create(['key' => 'title', 'label' => 'Nama SOP', 'type' => 'input', 'required' => true, 'sort' => 1]);

        $this->actingAs($leader)->delete(route('document-category.builder-steps.reset', $sop))->assertRedirect();

        $this->assertSame(
            array_column(DocumentBuilderTemplates::defaultQuestions($sop), 'label'),
            array_column(DocumentBuilderTemplates::for($sop->fresh())['questions'], 'label')
        );
    }

    public function test_answers_to_a_custom_step_are_validated_and_sent_to_the_ai(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['objective' => 'Tujuan dari AI'])]]]]]])]);

        $leader = $this->member(User::OD_LEADER);
        $sop = $this->sop();
        $sop->builderQuestions()->createMany([
            ['key' => 'title', 'label' => 'Nama SOP', 'type' => 'input', 'required' => true, 'sort' => 1],
            ['key' => 'q_lokasi01', 'label' => 'Lokasi Penerapan', 'type' => 'input', 'required' => true, 'sort' => 2],
        ]);

        $this->actingAs($leader)->post(route('document-builder.store', $sop), ['mode' => 'ai', 'answers' => ['title' => 'SOP Gudang']])
            ->assertSessionHasErrors('answers.q_lokasi01');

        $this->actingAs($leader)->post(route('document-builder.store', $sop), [
            'mode' => 'ai',
            'answers' => ['title' => 'SOP Gudang', 'q_lokasi01' => 'Gudang Cikarang', 'objective' => 'bukan langkah lagi'],
        ])->assertRedirect();

        $draft = DocumentDraft::sole();
        $this->assertSame(['title' => 'SOP Gudang', 'q_lokasi01' => 'Gudang Cikarang'], $draft->answers);
        $this->assertSame('Tujuan dari AI', $draft->content['objective']);
        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], '- Lokasi Penerapan: Gudang Cikarang'));
    }

    public function test_the_steps_page_never_links_to_the_form_of_an_inactive_category(): void
    {
        $leader = $this->member(User::OD_LEADER);
        $sop = $this->sop();

        $this->actingAs($leader)->get(route('document-category.builder-steps.edit', $sop))
            ->assertOk()
            ->assertSee('Lihat Form')
            ->assertSee(route('document-builder.form', $sop), false);

        // DocumentBuilderController::form menolak kategori nonaktif (404), jadi tautannya diganti tombol mati.
        $sop->update(['is_active' => false]);

        $this->actingAs($leader)->get(route('document-category.builder-steps.edit', $sop))
            ->assertOk()
            ->assertSee('Lihat Form')
            ->assertDontSee(route('document-builder.form', $sop), false)
            ->assertSee('sedang dinonaktifkan sehingga formulirnya tidak bisa dibuka');
    }

    public function test_a_category_with_builder_drafts_cannot_be_deleted(): void
    {
        $leader = $this->member(User::OD_LEADER);
        $category = DocumentCategory::create(['code' => 'KEB', 'name' => 'Kebijakan', 'is_active' => true, 'sort' => 99]);
        DocumentDraft::create(['document_category_id' => $category->id, 'user_id' => $leader->id, 'title' => 'Kebijakan Cuti']);

        $this->assertTrue($category->isInUse());
    }
}
