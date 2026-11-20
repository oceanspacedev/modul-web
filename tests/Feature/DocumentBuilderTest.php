<?php

namespace Tests\Feature;

use App\Models\DocumentCategory;
use App\Models\DocumentDraft;
use App\Models\User;
use App\Support\DocumentBuilderTemplates;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

/**
 * Dokumen Builder: tim OD memilih kategori dokumen, mengisi form bertahap, AI menyusun isinya, lalu draf diedit,
 * dipratinjau, dan diekspor ke Word.
 */
class DocumentBuilderTest extends TestCase
{
    private function member(string $access = User::OD_STAFF, string $name = 'Anggota OD'): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => $name]);
        $user->setOdReportAccess($access);

        return $user->fresh();
    }

    private function category(string $code): DocumentCategory
    {
        return DocumentCategory::where('code', $code)->firstOrFail();
    }

    private function draft(User $owner, string $code = 'SOP', array $attributes = []): DocumentDraft
    {
        return DocumentDraft::create([
            'document_category_id' => $this->category($code)->id,
            'user_id' => $owner->id,
            'title' => 'SOP '.$owner->nama_lengkap,
            'answers' => ['title' => 'SOP '.$owner->nama_lengkap],
            ...$attributes,
        ]);
    }

    private static function gemini(array $json): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]]];
    }

    private static function sopAnswers(): array
    {
        return [
            'title' => 'SOP Inspeksi HSE Lapangan',
            'department' => 'HSE',
            'objective' => 'Memastikan area kerja aman sebelum pekerjaan dimulai',
            'roles' => 'HSE Inspector: memeriksa, Supervisor: menindaklanjuti',
        ];
    }

    public function test_only_od_members_can_open_the_builder_and_it_sits_in_the_document_menu(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get(route('document-builder.index'))->assertRedirect();

        $this->actingAs($this->member())->get(route('document-builder.index'))
            ->assertOk()
            ->assertSeeInOrder(['Permintaan Masuk', 'Dokumen Builder', 'Approval Dokumen']);
    }

    public function test_every_active_category_gets_a_builder_form_including_new_ones(): void
    {
        DocumentCategory::create(['code' => 'KEB', 'name' => 'Kebijakan', 'description' => 'Kebijakan perusahaan', 'is_active' => true, 'sort' => 99]);
        DocumentCategory::create(['code' => 'OLD', 'name' => 'Kategori Lama', 'is_active' => false, 'sort' => 100]);
        $member = $this->member();

        $this->actingAs($member)->get(route('document-builder.create'))
            ->assertOk()
            ->assertSee(['Standard Operating Procedure', 'Instruksi Kerja', 'Job Description', 'Surat Edaran', 'Memo Internal', 'Kebijakan'])
            ->assertDontSee('Kategori Lama');

        foreach (DocumentCategory::active()->get() as $category) {
            $template = DocumentBuilderTemplates::for($category);

            $this->actingAs($member)->get(route('document-builder.form', $category))
                ->assertOk()
                ->assertSee($template['questions'][0]['label'])
                ->assertSee(end($template['questions'])['label']);
        }

        $this->assertSame('SOP', DocumentBuilderTemplates::for($this->category('SOP'))['key']);
        $this->assertSame('UMUM', DocumentBuilderTemplates::for(DocumentCategory::where('code', 'KEB')->first())['key']);
        $this->actingAs($member)->get(route('document-builder.form', DocumentCategory::where('code', 'OLD')->first()))->assertNotFound();
    }

    public function test_ai_composes_the_sop_from_the_answers_and_the_editor_opens(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(self::gemini([
            'objective' => 'Memastikan setiap area kerja diperiksa sebelum pekerjaan dimulai.',
            'scope' => 'Seluruh proyek lapangan.',
            'responsibilities' => [['title' => 'HSE Inspector', 'text' => 'Memeriksa area kerja']],
            'procedures' => [
                ['title' => 'Persiapan', 'text' => 'Inspector menyiapkan checklist.'],
                ['title' => 'Inspeksi', 'text' => 'Inspector memeriksa area.'],
            ],
            'kpi' => ['100% area diperiksa sebelum mulai kerja'],
            'tidak_dikenal' => 'dibuang',
        ]))]);

        $member = $this->member();
        $response = $this->actingAs($member)->post(route('document-builder.store', $this->category('SOP')), [
            'mode' => 'ai',
            'company' => 'PT Ocean Space',
            'answers' => self::sopAnswers(),
        ]);

        $draft = DocumentDraft::sole();
        $response->assertRedirect(route('document-builder.progress', $draft));
        $this->assertSame('SOP Inspeksi HSE Lapangan', $draft->title);
        $this->assertSame('HSE', $draft->department);
        $this->assertSame('PT Ocean Space', $draft->company);
        $this->assertSame('Memastikan setiap area kerja diperiksa sebelum pekerjaan dimulai.', $draft->content['objective']);
        $this->assertCount(2, $draft->content['procedures']);
        $this->assertArrayNotHasKey('tidak_dikenal', $draft->content);

        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'SOP Inspeksi HSE Lapangan')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'HSE Inspector: memeriksa')
            && str_contains($request['contents'][0]['parts'][0]['text'], '"procedures"'));

        $this->actingAs($member)->get(route('document-builder.progress', $draft))->assertOk()->assertSee('AI sedang menyusun');
        $this->actingAs($member)->getJson(route('document-builder.ai-status', $draft))->assertOk()->assertJson(['status' => 'done']);
        $this->assertNull($draft->fresh()->ai_token);
        $this->actingAs($member)->get(route('document-builder.progress', $draft))->assertRedirect(route('document-builder.edit', $draft));

        $this->actingAs($member)->get(route('document-builder.edit', $draft))
            ->assertOk()
            ->assertSee('Inspector memeriksa area.')
            ->assertSee('Penanggung Jawab');
    }

    public function test_ai_failure_is_reported_and_the_draft_can_still_be_filled_manually(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

        $member = $this->member();
        $this->actingAs($member)->post(route('document-builder.store', $this->category('SOP')), ['mode' => 'ai', 'answers' => self::sopAnswers()]);

        $draft = DocumentDraft::sole();
        $this->actingAs($member)->getJson(route('document-builder.ai-status', $draft))
            ->assertOk()
            ->assertJson(['status' => 'failed'])
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'quota exceeded'));
        $this->actingAs($member)->get(route('document-builder.edit', $draft))->assertOk();
    }

    public function test_manual_mode_only_needs_a_title_while_ai_needs_every_required_answer(): void
    {
        $member = $this->member();
        $sop = $this->category('SOP');

        $this->actingAs($member)->post(route('document-builder.store', $sop), ['mode' => 'ai', 'answers' => ['title' => 'SOP Tanpa Tujuan']])
            ->assertSessionHasErrors(['answers.department', 'answers.objective']);
        $this->assertDatabaseCount('document_drafts', 0);

        $this->actingAs($member)->post(route('document-builder.store', $sop), ['mode' => 'manual', 'answers' => ['title' => 'SOP Manual']])
            ->assertRedirect(route('document-builder.edit', DocumentDraft::sole()));
        Http::assertNothingSent();
    }

    public function test_ai_mode_without_a_gemini_key_falls_back_to_the_editor(): void
    {
        config(['services.gemini.api_key' => null]);

        $this->actingAs($this->member())->post(route('document-builder.store', $this->category('JD')), [
            'mode' => 'ai',
            'answers' => ['title' => 'Admin Operasional', 'department' => 'Operasional', 'purpose' => 'Menjaga administrasi tertib'],
        ])->assertRedirect(route('document-builder.edit', DocumentDraft::sole()))->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_editor_keeps_only_template_sections_in_order_and_drops_blank_rows(): void
    {
        $member = $this->member();
        $draft = $this->draft($member);

        $this->actingAs($member)->put(route('document-builder.update', $draft), [
            'title' => 'SOP Pengadaan Barang',
            'document_number' => 'SOP-PRC-001',
            'revision' => '01',
            'effective_date' => '2026-12-01',
            'approved_by' => 'Direktur Operasional',
            'content' => [
                'objective' => '  Pengadaan tertib.  ',
                'kpi' => ['Lead time ≤ 7 hari', '', '  '],
                'procedures' => [
                    'n2' => ['title' => 'Permintaan', 'text' => 'User mengisi form.'],
                    'n1' => ['title' => '', 'text' => ''],
                    'n3' => ['title' => 'Persetujuan', 'text' => 'Manager menyetujui.'],
                ],
                'bukan_bagian' => 'abaikan',
            ],
        ])->assertRedirect();

        $draft->refresh();
        $this->assertSame('SOP Pengadaan Barang', $draft->title);
        $this->assertSame('2026-12-01', $draft->effective_date->format('Y-m-d'));
        $this->assertSame('Pengadaan tertib.', $draft->content['objective']);
        $this->assertSame(['Lead time ≤ 7 hari'], $draft->content['kpi']);
        $this->assertSame(['Permintaan', 'Persetujuan'], array_column($draft->content['procedures'], 'title'));
        $this->assertArrayNotHasKey('bukan_bagian', $draft->content);
        $this->assertSame([], $draft->content['definitions']);

        $this->actingAs($member)->get(route('document-builder.show', $draft))
            ->assertOk()
            ->assertSeeInOrder(['SOP Pengadaan Barang', 'SOP-PRC-001', 'Direktur Operasional', 'Tujuan', 'Pengadaan tertib.', 'Prosedur', 'Permintaan', 'Persetujuan'])
            ->assertDontSee('Definisi</h2>', false);
    }

    public function test_word_export_is_a_valid_docx_with_escaped_text(): void
    {
        $member = $this->member();
        $draft = $this->draft($member, 'IK', [
            'title' => 'IK Forklift & Palet <Gudang>',
            'document_number' => 'IK-WH-002',
            'content' => [
                'steps' => [['title' => 'Cek unit', 'text' => "Periksa ban\nPeriksa rem"]],
                'safety' => ['Wajib helm & sepatu safety'],
            ],
        ]);

        $response = $this->actingAs($member)->get(route('document-builder.word', $draft));
        $response->assertOk()->assertDownload('IK - IK Forklift & Palet Gudang.docx');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertNotFalse(simplexml_load_string($xml), 'document.xml harus XML yang sah');
        $this->assertStringContainsString('IK Forklift &amp; Palet &lt;Gudang&gt;', $xml);
        $this->assertStringContainsString('IK-WH-002', $xml);
        $this->assertStringContainsString('Langkah Kerja', $xml);
        $this->assertStringContainsString('Periksa ban</w:t><w:br/>', $xml);
    }

    public function test_staff_only_manage_their_own_drafts_while_the_leader_sees_all(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF SATU');
        $other = $this->member(User::OD_STAFF, 'STAFF DUA');
        $leader = $this->member(User::OD_LEADER, 'LEADER OD');
        $mine = $this->draft($staff);
        $theirs = $this->draft($other);

        $this->actingAs($staff)->get(route('document-builder.index'))->assertSee($mine->title)->assertDontSee($theirs->title);
        $this->actingAs($staff)->get(route('document-builder.edit', $theirs))->assertRedirect();
        $this->actingAs($staff)->get(route('document-builder.word', $theirs))->assertRedirect();
        $this->actingAs($staff)->delete(route('document-builder.destroy', $theirs))->assertRedirect();
        $this->assertNotSoftDeleted($theirs);

        $this->actingAs($leader)->get(route('document-builder.index'))->assertSee($mine->title)->assertSee($theirs->title)->assertSee('STAFF DUA');
        $this->actingAs($leader)->get(route('document-builder.edit', $theirs))->assertOk();
        $this->actingAs($leader)->delete(route('document-builder.destroy', $theirs))->assertRedirect(route('document-builder.index'));
        $this->assertSoftDeleted($theirs);
    }

    public function test_recompose_updates_the_answers_and_replaces_the_content(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(self::gemini([
            'body' => 'Laporan bulanan dikumpulkan tanggal 25.',
            'actions' => ['Kirim laporan ke HRD'],
        ]))]);

        $member = $this->member();
        $draft = $this->draft($member, 'MEMO', ['title' => 'Memo Lama', 'content' => ['body' => 'Isi lama']]);

        $this->actingAs($member)->get(route('document-builder.answers', $draft))->assertOk()->assertSee('Susun Ulang dengan AI');

        $this->actingAs($member)->post(route('document-builder.recompose', $draft), [
            'mode' => 'ai',
            'answers' => ['title' => 'Pengumpulan Laporan Lebih Awal', 'department' => 'HRD', 'to' => 'Kepala Departemen', 'message' => 'Laporan dikumpulkan tanggal 25'],
        ])->assertRedirect(route('document-builder.progress', $draft));

        $draft->refresh();
        $this->assertSame('Pengumpulan Laporan Lebih Awal', $draft->title);
        $this->assertSame('Kepala Departemen', $draft->answers['to']);
        $this->assertSame('Laporan bulanan dikumpulkan tanggal 25.', $draft->content['body']);
    }
}
