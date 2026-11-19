<?php

namespace Tests\Feature;

use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kategori Dokumen: data master jenis dokumen dan tahapannya, dipakai form Permintaan Dokumen dan tim OD.
 */
class DocumentCategoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        Http::fake(['whatsapp.test/*' => Http::response(['status' => true])]);
        Storage::fake();
    }

    private function member(string $access, string $name = 'Anggota OD'): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => $name]);
        $user->setOdReportAccess($access);

        return $user->fresh();
    }

    private function category(string $code): DocumentCategory
    {
        return DocumentCategory::where('code', $code)->with('stages')->sole();
    }

    /**
     * Isi form ubah kategori dari data yang ada, dengan perubahan.
     *
     * @return array<string, mixed>
     */
    private function form(DocumentCategory $category, array $overrides = []): array
    {
        return [
            'code' => $category->code,
            'name' => $category->name,
            'description' => $category->description,
            'needs_observation' => $category->needs_observation ? '1' : null,
            'is_active' => $category->is_active ? '1' : null,
            'pic_user_id' => $category->pic_user_id,
            'sort' => $category->sort,
            'stages' => $category->stages->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all(),
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    private function requestPayload(string $code, array $overrides = []): array
    {
        return [
            'kategori' => DocumentCategory::where('code', $code)->value('id'),
            'nama' => 'Budi Pemohon',
            'divisi' => 'Gudang',
            'departemen' => 'Warehouse',
            'jabatan' => 'Leader',
            'whatsapp' => '081234567890',
            'email' => 'budi@example.com',
            'proses' => [['judul' => 'Admin Gudang', 'tujuan' => 'Uraian tugas admin gudang belum ada.', 'prioritas' => 'High']],
            ...$overrides,
        ];
    }

    public function test_default_categories_are_seeded_with_their_stages(): void
    {
        $this->assertSame(['SOP', 'SE', 'MEMO', 'IK', 'JD', 'Dokumen Lainnya'], DocumentCategory::ordered()->pluck('code')->all());

        $ik = $this->category('IK');
        $this->assertTrue($ik->needs_observation);
        $this->assertCount(8, $ik->stages);
        $this->assertSame('Release', $ik->finalStage());
        $this->assertSame(['Diajukan', 'Drafting', 'Proses review PIC', 'Proses review OD Leader', 'Proses Esign', 'Release'], $this->category('JD')->stageNames());
    }

    public function test_only_od_leader_manages_categories(): void
    {
        $staff = $this->member(User::OD_STAFF);
        $leader = $this->member(User::OD_LEADER);

        $this->actingAs($staff)->get(route('document-category.index'))->assertRedirect();
        $this->actingAs($staff)->post(route('document-category.store'), ['code' => 'BA', 'name' => 'Berita Acara'])->assertRedirect();
        $this->assertDatabaseMissing('document_categories', ['code' => 'BA']);

        $this->actingAs($leader)->get(route('document-category.index'))->assertOk()->assertSee('Instruksi Kerja')->assertSee('Tambah Kategori');
        $this->actingAs($leader)->get(route('document-category.index', ['search' => 'job']))->assertOk()
            ->assertSee('Job Description')->assertDontSee('Instruksi Kerja');
        // Menu ada di grup Layanan, hanya untuk Leader.
        $this->actingAs($leader)->get(route('document-request.mine'))->assertSee('Kategori Dokumen');
        $this->actingAs($staff)->get(route('document-request.mine'))->assertDontSee('Kategori Dokumen');
    }

    public function test_leader_attaches_an_example_file_that_requesters_can_open(): void
    {
        $leader = $this->member(User::OD_LEADER);
        $jd = $this->category('JD');
        $ik = $this->category('IK');

        // IK punya contoh bawaan; JD belum punya contoh.
        $this->get(route('document-request.create'))->assertOk()
            ->assertSee(route('document-request.example', $ik))->assertDontSee(route('document-request.example', $jd));
        $this->get(route('document-request.example', $jd))->assertNotFound();
        $this->get(route('document-request.example', $ik))->assertOk()->assertSee('contoh-ik.pdf');

        $this->actingAs($leader)->put(route('document-category.update', $jd), $this->form($jd, ['example' => UploadedFile::fake()->create('virus.exe', 10)]))
            ->assertSessionHasErrors('example');

        $this->actingAs($leader)->put(route('document-category.update', $jd), $this->form($jd, ['example' => UploadedFile::fake()->create('Contoh JD.pdf', 50, 'application/pdf')]))
            ->assertSessionHas('success');
        $jd->refresh();
        $this->assertSame('Contoh JD.pdf', $jd->example_name);
        Storage::assertExists($jd->example_path);
        $this->actingAs($leader)->get(route('document-category.index'))->assertSee('fa-paperclip', false);

        auth()->logout();
        $this->get(route('document-request.create'))->assertSee(route('document-request.example', $jd));
        $this->get(route('document-request.example', $jd))->assertOk()->assertSee('Contoh Dokumen JD')->assertSee(route('document-request.example-file', $jd));
        $this->get(route('document-request.example-file', $jd))->assertRedirectContains($jd->example_path);

        // Ganti file: file lama dihapus. Simpan tanpa file: contoh tetap.
        $old = $jd->example_path;
        $this->actingAs($leader)->put(route('document-category.update', $jd), $this->form($jd, ['example' => UploadedFile::fake()->create('jd.docx', 20)]));
        Storage::assertMissing($old);
        $this->actingAs($leader)->put(route('document-category.update', $jd->fresh()), $this->form($jd->fresh()));
        $jd->refresh();
        $this->assertSame('jd.docx', $jd->example_name);
        $this->get(route('document-request.example', $jd))->assertSee('Unduh contoh');

        // Hapus contoh.
        $path = $jd->example_path;
        $this->actingAs($leader)->put(route('document-category.update', $jd), $this->form($jd, ['remove_example' => '1']));
        Storage::assertMissing($path);
        $this->assertNull($jd->fresh()->example_path);
        $this->get(route('document-request.example-file', $jd))->assertNotFound();
    }

    public function test_leader_adds_category_and_edits_its_stages(): void
    {
        $leader = $this->member(User::OD_LEADER, 'LEADER OD');

        $this->actingAs($leader)->post(route('document-category.store'), ['code' => 'BA', 'name' => 'Berita Acara'])
            ->assertRedirect(route('document-category.edit', DocumentCategory::where('code', 'BA')->sole()));
        $ba = $this->category('BA');
        $this->assertSame(array_keys(DocumentCategory::DEFAULT_STAGES), $ba->stageNames());

        $this->actingAs($leader)->get(route('document-category.edit', $ba))->assertOk()->assertSee('Drafting');

        $this->actingAs($leader)->put(route('document-category.update', $ba), $this->form($ba, [
            'pic_user_id' => $leader->id,
            'stages' => [
                ['name' => 'Diterima', 'description' => 'Pengajuan diterima.'],
                ['id' => $ba->stages->firstWhere('name', 'Drafting')->id, 'name' => 'Penyusunan'],
                ['name' => 'Selesai'],
            ],
        ]))->assertRedirect(route('document-category.edit', $ba))->assertSessionHas('success');

        $ba = $this->category('BA');
        $this->assertSame(['Diterima', 'Penyusunan', 'Selesai'], $ba->stageNames());
        $this->assertSame($leader->id, $ba->picUser()->id);

        // Validasi: minimal 2 tahap, nama tidak kembar, "Dibatalkan" dipakai sistem.
        $this->actingAs($leader)->put(route('document-category.update', $ba), $this->form($ba, ['stages' => [['name' => 'Satu']]]))
            ->assertSessionHasErrors('stages');
        $this->actingAs($leader)->put(route('document-category.update', $ba), $this->form($ba, ['stages' => [['name' => 'Sama'], ['name' => 'sama']]]))
            ->assertSessionHasErrors('stages.1.name');
        $this->actingAs($leader)->put(route('document-category.update', $ba), $this->form($ba, ['stages' => [['name' => 'Awal'], ['name' => 'Dibatalkan']]]))
            ->assertSessionHasErrors('stages.1.name');

        // Kategori yang belum dipakai boleh dihapus.
        $this->actingAs($leader)->delete(route('document-category.destroy', $ba))->assertSessionHas('success');
        $this->assertModelMissing($ba);
    }

    public function test_request_for_category_without_observation_uses_its_code_stages_and_pic(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF JD');
        DocumentCategory::where('code', 'JD')->update(['pic_user_id' => $staff->id]);

        $this->get(route('document-request.create', ['kategori' => 'jd']))->assertOk()->assertSee('Job Description');

        // JD tidak butuh observasi, jadi tanggal observasi boleh kosong.
        $this->post(route('document-request.store'), $this->requestPayload('JD'))->assertRedirect();

        $request = DocumentRequest::sole();
        $this->assertStringStartsWith('JD-20261118-', $request->code);
        $this->assertMatchesRegularExpression(DocumentRequest::CODE_PATTERN, $request->code);
        $this->assertNull($request->obs_tanggal_1);

        $document = $request->processes()->sole();
        $this->assertSame('JD', $document->jenis);
        $this->assertSame('Diajukan', $document->stage);
        $this->assertSame($staff->id, $document->pic_user_id);

        $this->get(route('document-request.track', ['kode' => $request->code]))->assertOk()
            ->assertSee('Job Description')->assertDontSee('Dijadwalkan observasi');

        // IK tetap wajib tanggal observasi.
        $this->post(route('document-request.store'), $this->requestPayload('IK', ['idempotency_key' => 'ik-1']))->assertSessionHasErrors('obs_tanggal_1');

        // Tahap di luar tahapan JD ditolak, tahap terakhir JD menutup dokumen.
        $leader = $this->member(User::OD_LEADER);
        $form = fn (string $stage) => [
            'updated_at' => $request->fresh()->updated_at->toIso8601String(),
            'proses' => [$document->id => ['stage' => $stage, 'file' => UploadedFile::fake()->create('jd.pdf', 50, 'application/pdf')]],
        ];
        $this->actingAs($leader)->put(route('od-report.requests.update', $request), $form('Dalam proses observasi'))->assertSessionHasErrors('proses.'.$document->id.'.stage');
        $this->actingAs($leader)->put(route('od-report.requests.update', $request), $form('Release'))->assertSessionHas('success');
        $this->assertTrue($document->fresh()->status->isDone());
        $this->assertSame(100, $request->fresh()->load('category.stages', 'processes.status')->progress());
    }

    public function test_inactive_category_is_hidden_and_rejected(): void
    {
        DocumentCategory::where('code', 'MEMO')->update(['is_active' => false]);

        $this->get(route('document-request.create'))->assertOk()->assertDontSee('Memo Internal');
        $this->post(route('document-request.store'), $this->requestPayload('MEMO'))->assertSessionHasErrors('kategori');
        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_renaming_a_stage_moves_documents_and_used_stages_or_codes_are_locked(): void
    {
        $leader = $this->member(User::OD_LEADER);
        $this->post(route('document-request.store'), $this->requestPayload('JD'));
        $document = OdDocument::sole();
        $jd = $this->category('JD');
        $stages = $jd->stages;

        // Ganti nama tahap "Diajukan" → dokumen di tahap itu ikut berganti.
        $renamed = $this->form($jd);
        $renamed['stages'][0]['name'] = 'Diterima';
        $this->actingAs($leader)->put(route('document-category.update', $jd), $renamed)->assertSessionHas('success');
        $this->assertSame('Diterima', $document->fresh()->stage);

        // Form hanya berisi judul: penjelasan tahap yang tidak diganti nama tetap ada, yang diganti nama dikosongkan.
        $jd = $this->category('JD');
        $this->assertNull($jd->stageDescription('Diterima'));
        $this->assertSame(DocumentCategory::DEFAULT_STAGES['Drafting'], $jd->stageDescription('Drafting'));

        // Tahap yang masih dipakai tidak bisa dihapus, kode yang sudah dipakai tidak bisa diubah.
        $jd = $this->category('JD');
        $withoutFirst = $this->form($jd);
        array_shift($withoutFirst['stages']);
        $this->actingAs($leader)->put(route('document-category.update', $jd), $withoutFirst)->assertSessionHasErrors('stages');
        $this->actingAs($leader)->put(route('document-category.update', $jd), $this->form($jd, ['code' => 'JOBDESC']))->assertSessionHasErrors('code');
        $this->assertSame('JD', $jd->fresh()->code);

        $this->actingAs($leader)->delete(route('document-category.destroy', $jd))->assertSessionHas('error');
        $this->assertModelExists($jd);

        // Dokumen yang sudah selesai pindah ke tahap terakhir yang baru.
        $document->update(['stage' => 'Release', 'od_document_status_id' => OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE)->id]);
        $appended = $this->form($jd);
        $appended['stages'][] = ['name' => 'Diarsipkan'];
        $this->actingAs($leader)->put(route('document-category.update', $jd), $appended)->assertSessionHas('success');
        $this->assertSame('Diarsipkan', $document->fresh()->stage);
        $this->assertCount($stages->count() + 1, $this->category('JD')->stages);
    }
}
