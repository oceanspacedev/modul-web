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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Dokumen dari Permintaan Dokumen wajib punya file saat dirilis, dan file itu dikirim ke pemohon lewat WhatsApp.
 */
class DocumentReleaseFileTest extends TestCase
{
    private User $leader;

    private DocumentRequest $request;

    private OdDocument $document;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        Http::fake(['whatsapp.test/*' => Http::response(['status' => true])]);
        Storage::fake();

        $this->leader = User::factory()->staff()->create();
        $this->leader->setOdReportAccess(User::OD_LEADER);
        $this->leader = $this->leader->fresh();

        $this->post(route('document-request.store'), [
            'kategori' => DocumentCategory::where('code', 'JD')->value('id'),
            'nama' => 'Budi Pemohon', 'divisi' => 'Gudang', 'departemen' => 'Warehouse', 'jabatan' => 'Leader',
            'whatsapp' => '081234567890', 'email' => 'budi@example.com',
            'proses' => [['judul' => 'JD Admin Gudang', 'tujuan' => 'Uraian tugas admin gudang belum ada.', 'prioritas' => 'High']],
        ]);
        $this->request = DocumentRequest::sole();
        $this->document = $this->request->processes()->sole();
    }

    private function pdf(string $name = 'jd-admin-gudang.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 200, 'application/pdf');
    }

    /** @param array<string, mixed> $input */
    private function updateRequest(array $input): TestResponse
    {
        return $this->actingAs($this->leader)->put(route('od-report.requests.update', $this->request), [
            'updated_at' => $this->request->fresh()->updated_at->toIso8601String(),
            'proses' => [$this->document->id => $input],
        ]);
    }

    /**
     * Isi pesan WhatsApp yang dikirim ke pemohon, berurutan.
     *
     * @return list<array<string, mixed>>
     */
    private function messagesToRequester(): array
    {
        return Http::recorded()->map(fn (array $pair) => $pair[0]->data())
            ->filter(fn (array $body) => $body['recipient']['value'] === '081234567890')
            ->pluck('message')->values()->all();
    }

    /** Teks rilis lalu file dikirim sebagai dokumen WhatsApp, bukan link. */
    private function assertReleaseSentWithFile(OdDocument $document): void
    {
        [$text, $file] = array_slice($this->messagesToRequester(), -2);

        $this->assertSame('text', $text['type']);
        $this->assertStringContainsString('JD Admin Gudang', $text['text']);
        $this->assertStringContainsString('terlampir', $text['text']);
        $this->assertStringNotContainsString('http', str_replace(route('document-request.track', ['kode' => $this->request->code]), '', $text['text']));

        $this->assertSame('document', $file['type']);
        $this->assertSame('File dokumen: JD Admin Gudang', $file['text']);
        $this->assertSame($document->file_name, $file['attachment']['filename']);
        $this->assertStringContainsString($document->file_path, $file['attachment']['url']);
    }

    public function test_release_from_incoming_requests_needs_a_file_and_sends_its_link(): void
    {
        $this->updateRequest(['stage' => 'Release'])->assertSessionHasErrors('proses.'.$this->document->id.'.file');
        $this->assertSame('Diajukan', $this->document->fresh()->stage);

        $this->updateRequest(['stage' => 'Release', 'file' => $this->pdf()])->assertSessionHas('success');

        $document = $this->document->fresh();
        $this->assertSame('Release', $document->stage);
        $this->assertTrue($document->status->isDone());
        $this->assertSame('jd-admin-gudang.pdf', $document->file_name);
        Storage::assertExists($document->file_path);

        // WA ke pemohon: teks rilis, lalu file-nya sebagai dokumen WhatsApp.
        $this->assertReleaseSentWithFile($document);
        $this->assertSame('application/pdf', last($this->messagesToRequester())['attachment']['mime_type']);

        $this->get($document->fileLink())->assertRedirectContains($document->file_path);
        $this->get(route('document-request.file', $document))->assertForbidden();
        $this->get($document->fileLink().'x')->assertForbidden();

        // Halaman lacak menampilkan tombol file.
        $this->get(route('document-request.track', ['kode' => $this->request->code]))->assertSee('Buka file');
    }

    public function test_file_link_only_works_after_release(): void
    {
        $this->updateRequest(['stage' => 'Drafting', 'file' => $this->pdf()])->assertSessionHas('success');
        $document = $this->document->fresh();

        $this->assertTrue($document->hasFile());
        $this->get($document->fileLink())->assertNotFound();
        $this->get(route('document-request.track', ['kode' => $this->request->code]))->assertDontSee('Buka file');

        // Sudah punya file: rilis tidak perlu unggah lagi.
        $this->updateRequest(['stage' => 'Release'])->assertSessionHas('success');
        $this->get($document->fileLink())->assertRedirectContains($document->file_path);
    }

    public function test_quick_status_and_edit_form_need_a_file_to_release(): void
    {
        $done = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE);
        $this->actingAs($this->leader)->get(route('od-report.documents.index'))->assertOk()->assertSee('id="releaseModal"', false);

        $this->actingAs($this->leader)->patch(route('od-report.documents.status', $this->document), ['od_document_status_id' => $done->id])
            ->assertSessionHasErrors('file', null, 'release');
        $this->assertFalse($this->document->fresh()->status->isDone());

        $form = fn (array $extra) => [
            'title' => 'JD Admin Gudang', 'jenis' => 'JD', 'od_document_status_id' => $done->id, ...$extra,
        ];
        $this->actingAs($this->leader)->put(route('od-report.documents.update', $this->document), $form([]))->assertSessionHasErrors('file');
        $this->assertFalse($this->document->fresh()->status->isDone());

        $this->actingAs($this->leader)->put(route('od-report.documents.update', $this->document), $form(['file' => $this->pdf()]))
            ->assertSessionHasNoErrors();
        $document = $this->document->fresh();
        $this->assertTrue($document->status->isDone());
        $this->assertSame('Release', $document->stage);
        $this->assertReleaseSentWithFile($document);

        // Tim OD bisa membuka file dari Approval Dokumen.
        $this->actingAs($this->leader)->get(route('od-report.documents.file', $document))->assertRedirectContains($document->file_path);
    }

    public function test_quick_status_release_with_file_and_file_type_rules(): void
    {
        $done = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE);

        $this->actingAs($this->leader)->patch(route('od-report.documents.status', $this->document), [
            'od_document_status_id' => $done->id, 'file' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertSessionHasErrors('file', null, 'release');
        $this->actingAs($this->leader)->patch(route('od-report.documents.status', $this->document), [
            'od_document_status_id' => $done->id, 'file' => UploadedFile::fake()->create('besar.pdf', 51201, 'application/pdf'),
        ])->assertSessionHasErrors('file', null, 'release');

        $this->actingAs($this->leader)->patch(route('od-report.documents.status', $this->document), [
            'od_document_status_id' => $done->id,
            'file' => UploadedFile::fake()->create('jd.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHas('success');
        $this->assertTrue($this->document->fresh()->isReleased());

        // File Word dikirim sebagai dokumen dengan jenis yang sesuai.
        $this->assertSame([
            'filename' => 'jd.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], collect(last($this->messagesToRequester())['attachment'])->only(['filename', 'mime_type'])->all());
    }

    public function test_manual_documents_can_finish_without_a_file(): void
    {
        $manual = OdDocument::create([
            'title' => 'SOP Manual', 'jenis' => 'SOP', 'pic_user_id' => $this->leader->id, 'submitted_at' => today(), 'month' => '2026-11',
            'od_document_status_id' => OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN)->id,
        ]);
        $done = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE);

        $this->actingAs($this->leader)->patch(route('od-report.documents.status', $manual), ['od_document_status_id' => $done->id])
            ->assertSessionHas('success');
        $this->assertTrue($manual->fresh()->status->isDone());
    }
}
