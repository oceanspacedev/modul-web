<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\OdDocument;
use App\Models\OdDocumentAttachment;
use App\Models\OdDocumentStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Revisi Permintaan Dokumen: badan usaha, pengertian jenis dokumen, uraian dengan lampiran, kategori bertemplate
 * (Jobdesk), tiga status (Pengajuan diterima → On Progress → Selesai), komentar tim OD, dan detail admin.
 */
class DocumentRequestRevisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 10:00:00');
        Http::fake(['whatsapp.test/*' => Http::response(['status' => true])]);
        Storage::fake();
    }

    private function member(string $access, string $name = 'ANGGOTA OD'): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => $name]);
        $user->setOdReportAccess($access);

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function payload(string $code = 'SOP', array $process = [], array $overrides = []): array
    {
        return [
            'kategori' => DocumentCategory::where('code', $code)->value('id'),
            'nama' => 'Budi Pemohon',
            'divisi' => 'Gudang',
            'departemen' => 'Warehouse',
            'jabatan' => 'Leader',
            'whatsapp' => '081234567890',
            'email' => 'budi@example.com',
            'badan_usaha' => 'TEST AREA',
            'proses' => [['judul' => 'Penerimaan barang', 'tujuan' => 'Supaya penerimaan barang seragam.', 'prioritas' => 'High', ...$process]],
            ...$overrides,
        ];
    }

    private function submit(array $payload): TestResponse
    {
        return $this->post(route('document-request.store'), $payload);
    }

    /** Kategori JD dengan template pengisian. */
    private function jobdeskWithTemplate(): DocumentCategory
    {
        $jd = DocumentCategory::where('code', 'JD')->firstOrFail();
        $jd->forceFill(['template_path' => 'kategori-dokumen/template/jd.docx', 'template_name' => 'Template JD.docx'])->save();
        Storage::put('kategori-dokumen/template/jd.docx', 'template');

        return $jd;
    }

    /**
     * Teks pesan WhatsApp ke pemohon, berurutan.
     *
     * @return list<string>
     */
    private function textsToRequester(): array
    {
        return Http::recorded()->map(fn (array $pair) => $pair[0]->data())
            ->filter(fn (array $body) => $body['recipient']['value'] === '081234567890')
            ->map(fn (array $body) => (string) ($body['message']['text'] ?? ''))->values()->all();
    }

    public function test_form_has_business_units_definitions_and_no_stage_list(): void
    {
        Area::create(['name' => 'CV COMPLETE SELULAR']);

        $html = $this->get(route('document-request.create'))->assertOk()->getContent();
        $this->assertStringContainsString('<option value="CV COMPLETE SELULAR"', $html);
        $this->assertStringContainsString('data-feedback="badan_usaha"', $html);
        $this->assertStringContainsString('Dokumen yang berisi standar dan tahapan prosedur kerja', $html);
        $this->assertStringContainsString('Dokumen yang menjelaskan tugas, tanggung jawab, wewenang', $html);
        $this->assertStringNotContainsString('Tahapan', $html);
        $this->assertStringContainsString('Uraian', $html);
        $this->assertStringNotContainsString('Tujuan / alasan', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);

        // Karyawan yang login: badan usaha terisi dari areanya.
        $user = User::factory()->staff()->create(['area_id' => 1]);
        $this->actingAs($user)->get(route('document-request.create'))->assertOk()->assertSee('<option value="TEST AREA" selected>', false);
    }

    public function test_business_unit_is_required_and_stored_on_the_request_and_its_documents(): void
    {
        $this->submit($this->payload(overrides: ['badan_usaha' => 'BUKAN BADAN USAHA']))->assertSessionHasErrors('badan_usaha');
        $this->submit($this->payload(overrides: ['badan_usaha' => '']))->assertSessionHasErrors('badan_usaha');
        $this->assertSame(0, DocumentRequest::count());

        $this->submit($this->payload())->assertSessionHasNoErrors();
        $request = DocumentRequest::sole();
        $this->assertSame('TEST AREA', $request->badan_usaha);
        $this->assertSame('TEST AREA', $request->processes()->sole()->badan_usaha);
        $this->assertSame(OdDocument::STAGE_RECEIVED, $request->processes()->sole()->stage);
    }

    public function test_attachments_accept_office_pdf_and_visio_within_limits(): void
    {
        $files = [
            UploadedFile::fake()->create('alur.pdf', 300, 'application/pdf'),
            UploadedFile::fake()->create('alur.vsdx', 300, 'application/vnd.ms-visio.drawing'),
            UploadedFile::fake()->create('data.xlsx', 300),
        ];
        $this->submit($this->payload(process: ['lampiran' => $files]))->assertSessionHasNoErrors();

        $attachments = OdDocumentAttachment::orderBy('id')->get();
        $this->assertSame(['alur.pdf', 'alur.vsdx', 'data.xlsx'], $attachments->pluck('name')->all());
        $this->assertTrue($attachments->every(fn ($a) => $a->kind === OdDocumentAttachment::KIND_LAMPIRAN));
        $this->assertStringEndsWith('.vsdx', $attachments[1]->path);
        $attachments->each(fn ($a) => Storage::assertExists($a->path));

        // Jenis lain, lebih dari 5 file, atau total lebih dari 10 MB ditolak.
        $this->submit($this->payload(process: ['lampiran' => [UploadedFile::fake()->create('virus.exe', 10)]], overrides: ['idempotency_key' => 'b']))
            ->assertSessionHasErrors('proses.0.lampiran.0');
        $six = array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 10, 'application/pdf'), range(1, 6));
        $this->submit($this->payload(process: ['lampiran' => $six], overrides: ['idempotency_key' => 'c']))->assertSessionHasErrors('proses.0.lampiran');
        $big = [UploadedFile::fake()->create('a.pdf', 6000, 'application/pdf'), UploadedFile::fake()->create('b.pdf', 6000, 'application/pdf')];
        $this->submit($this->payload(process: ['lampiran' => $big], overrides: ['idempotency_key' => 'd']))->assertSessionHasErrors('proses.0.lampiran');
        $this->assertSame(1, DocumentRequest::count());
    }

    public function test_only_the_od_team_that_sees_the_request_can_download_its_attachments(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF SOP');
        $other = $this->member(User::OD_STAFF, 'STAFF LAIN');
        DocumentCategory::where('code', 'SOP')->update(['pic_user_id' => $staff->id]);
        $this->submit($this->payload(process: ['lampiran' => [UploadedFile::fake()->create('alur.pdf', 50, 'application/pdf')]]));
        $request = DocumentRequest::sole();
        $attachment = OdDocumentAttachment::sole();

        $this->actingAs($staff)->get(route('od-report.requests.show', $request))->assertOk()->assertSee('alur.pdf')
            ->assertSee(route('od-report.requests.attachment', [$request, $attachment]));
        $this->actingAs($staff)->get(route('od-report.requests.attachment', [$request, $attachment]))->assertRedirectContains($attachment->path);
        $this->actingAs($other)->get(route('od-report.requests.attachment', [$request, $attachment]))->assertRedirect();

        // Lampiran pengajuan lain tidak bisa dibuka lewat pengajuan ini.
        $this->submit($this->payload(overrides: ['idempotency_key' => 'kedua']));
        $second = DocumentRequest::latest('id')->first();
        $this->actingAs($this->member(User::OD_LEADER))->get(route('od-report.requests.attachment', [$second, $attachment]))->assertNotFound();
    }

    public function test_template_category_asks_for_title_kind_priority_and_the_filled_template(): void
    {
        $jd = $this->jobdeskWithTemplate();

        $html = $this->get(route('document-request.create', ['kategori' => 'JD']))->assertOk()->getContent();
        $this->assertStringContainsString(route('document-request.template', $jd), $html);
        $this->assertStringContainsString('name="proses[0][jenis_pengajuan]"', $html);
        $this->assertStringContainsString('name="proses[0][template]"', $html);
        $this->get(route('document-request.template', $jd))->assertRedirectContains('kategori-dokumen/template/jd.docx');

        // Tanpa template terisi dan keterangan: ditolak. Uraian tidak wajib.
        $this->submit($this->payload('JD', ['tujuan' => '']))->assertSessionHasErrors(['proses.0.template', 'proses.0.jenis_pengajuan'])
            ->assertSessionDoesntHaveErrors('proses.0.tujuan');

        $this->submit($this->payload('JD', [
            'judul' => 'Admin Gudang', 'tujuan' => '', 'jenis_pengajuan' => 'Revisi',
            'template' => UploadedFile::fake()->create('jd-admin-gudang.docx', 100),
        ]))->assertSessionHasNoErrors();

        $document = OdDocument::sole();
        $this->assertSame('Revisi', $document->jenis_pengajuan);
        $this->assertNull($document->tujuan);
        $attachment = $document->attachments()->sole();
        $this->assertSame(OdDocumentAttachment::KIND_TEMPLATE, $attachment->kind);
        $this->assertSame('jd-admin-gudang.docx', $attachment->name);

        // Tim OD melihat keterangan dan template terisi di detail.
        $request = DocumentRequest::sole();
        $this->actingAs($this->member(User::OD_LEADER))->get(route('od-report.requests.show', $request))->assertOk()
            ->assertSee('Job Description revisi')->assertSee('Template terisi')->assertSee('jd-admin-gudang.docx');

        // Kategori tanpa template tetap memakai form biasa, dan template-nya tidak bisa diunduh.
        $this->get(route('document-request.template', DocumentCategory::where('code', 'SOP')->first()))->assertNotFound();
    }

    public function test_od_leader_uploads_and_removes_a_category_template(): void
    {
        $leader = $this->member(User::OD_LEADER);
        $jd = DocumentCategory::with('stages')->where('code', 'JD')->firstOrFail();
        $form = [
            'code' => 'JD', 'name' => $jd->name, 'is_active' => 1,
            'stages' => $jd->stages->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all(),
        ];

        $this->actingAs($leader)->put(route('document-category.update', $jd), [...$form, 'template' => UploadedFile::fake()->create('template-jd.docx', 50)])
            ->assertSessionHas('success');
        $jd->refresh();
        $this->assertTrue($jd->hasTemplate());
        $this->assertSame('template-jd.docx', $jd->template_name);
        Storage::assertExists($jd->template_path);
        $this->actingAs($leader)->get(route('document-category.edit', $jd))->assertOk()->assertSee('template-jd.docx');

        $path = $jd->template_path;
        $this->actingAs($leader)->put(route('document-category.update', $jd), [...$form, 'remove_template' => 1])->assertSessionHas('success');
        $this->assertFalse($jd->fresh()->hasTemplate());
        Storage::assertMissing($path);
    }

    public function test_tracking_shows_three_statuses_and_comments_from_the_od_team(): void
    {
        $leader = $this->member(User::OD_LEADER, 'SEKAR OD');
        $this->submit($this->payload());
        $request = DocumentRequest::sole();
        $document = $request->processes()->sole();

        $track = fn () => $this->get(route('document-request.track', ['kode' => $request->code]));
        $track()->assertOk()->assertSeeInOrder(['Riwayat', 'Pengajuan diterima', 'On Progress', 'Selesai', 'Komentar'])
            ->assertSee('Belum ada komentar')->assertDontSee('Arti setiap tahap')->assertDontSee('Drafting');

        // Komentar: tersimpan, tampil di lacak, dan dikirim ke WhatsApp pemohon.
        $this->actingAs($leader)->post(route('od-report.requests.comment', $request), ['body' => 'Pengajuan akan diproses secepatnya'])
            ->assertRedirect(route('od-report.requests.show', $request))->assertSessionHas('success');
        $this->assertSame('Pengajuan akan diproses secepatnya', $request->comments()->sole()->body);
        $this->assertStringContainsString('Pengajuan akan diproses secepatnya', last($this->textsToRequester()));
        $track()->assertSee('SEKAR OD')->assertSee('Pengajuan akan diproses secepatnya');
        $this->actingAs($leader)->post(route('od-report.requests.comment', $request), ['body' => ''])->assertSessionHasErrors('body');

        // Tombol Proses: Pengajuan diterima → On Progress.
        $this->actingAs($leader)->put(route('od-report.requests.update', $request), [
            'updated_at' => $request->fresh()->updated_at->toIso8601String(), 'aksi' => 'proses',
        ])->assertSessionHas('success');
        $this->assertSame(OdDocument::STAGE_PROGRESS, $document->fresh()->stage);
        $this->assertSame(OdDocument::STAGE_PROGRESS, $request->fresh()->load('processes.status')->stage());

        $timeline = collect($request->fresh()->load('processes.status', 'histories')->timeline());
        $this->assertSame([true, true, false], $timeline->pluck('done')->all());
        $this->assertNotNull($timeline[1]['at']);
        $this->assertNull($timeline[2]['at']);

        // Selesai: dokumen rilis dikirim ke WhatsApp dan bisa diunduh di lacak.
        $this->actingAs($leader)->put(route('od-report.requests.update', $request), [
            'updated_at' => $request->fresh()->updated_at->toIso8601String(),
            'proses' => [$document->id => ['stage' => OdDocument::STAGE_DONE, 'file' => UploadedFile::fake()->create('sop.pdf', 50, 'application/pdf')]],
        ])->assertSessionHas('success');
        $this->assertTrue($document->fresh()->status->isDone());
        $this->assertSame('document', Http::recorded()->map(fn ($pair) => $pair[0]->data())->last()['message']['type']);
        $track()->assertSee('Buka file');
        $this->assertSame([true, true, true], collect($request->fresh()->load('processes.status', 'histories')->timeline())->pluck('done')->all());
    }

    public function test_staff_outside_the_request_cannot_comment(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF LAIN');
        $this->submit($this->payload());
        $request = DocumentRequest::sole();

        $this->actingAs($staff)->post(route('od-report.requests.comment', $request), ['body' => 'Halo'])->assertRedirect();
        $this->assertSame(0, $request->comments()->count());
    }

    public function test_migration_maps_category_stages_to_the_three_request_statuses(): void
    {
        $this->submit($this->payload());
        $request = DocumentRequest::sole();
        $open = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN)->id;
        $done = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE)->id;
        $make = fn (string $stage, int $status) => DB::table('od_documents')->insertGetId([
            'title' => $stage, 'jenis' => 'SOP', 'badan_usaha' => '-', 'od_document_status_id' => $status, 'submitted_at' => today(),
            'month' => '2026-11', 'document_request_id' => $request->id, 'stage' => $stage, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ids = ['Diajukan' => $make('Diajukan', $open), 'Proses review PIC' => $make('Proses review PIC', $open), 'Release' => $make('Release', $done)];
        $manual = DB::table('od_documents')->insertGetId([
            'title' => 'Manual', 'jenis' => 'SOP', 'badan_usaha' => '-', 'od_document_status_id' => $open, 'submitted_at' => today(),
            'month' => '2026-11', 'stage' => 'Drafting', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('document_request_histories')->insert(['document_request_id' => $request->id, 'status' => 'Drafting', 'created_at' => now()]);

        $migration = require database_path('migrations/2026_11_25_100100_use_three_stages_for_document_requests.php');
        $migration->up();

        $this->assertSame(OdDocument::STAGE_RECEIVED, DB::table('od_documents')->where('id', $ids['Diajukan'])->value('stage'));
        $this->assertSame(OdDocument::STAGE_PROGRESS, DB::table('od_documents')->where('id', $ids['Proses review PIC'])->value('stage'));
        $this->assertSame(OdDocument::STAGE_DONE, DB::table('od_documents')->where('id', $ids['Release'])->value('stage'));
        $this->assertSame('Drafting', DB::table('od_documents')->where('id', $manual)->value('stage'));
        $this->assertTrue(DB::table('document_request_histories')->where('status', OdDocument::STAGE_PROGRESS)->exists());
        $this->assertFalse(DB::table('document_request_histories')->where('status', 'Drafting')->exists());
    }
}
