<?php

namespace Tests\Feature;

use App\Jobs\SendHelpdeskWebhook;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestRecipient;
use App\Models\OdDocument;
use App\Models\OdDocumentAttachment;
use App\Models\OdDocumentStatus;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Integrasi helpdesk (kontrak v1): API kategori, definisi form, pembuatan tiket, snapshot, dan webhook ticket.updated.
 */
class HelpdeskIntegrationTest extends TestCase
{
    private const TOKEN = 'token-uji-helpdesk';

    private const WEBHOOK_URL = 'https://helpdesk.test/api/integrations/1/events';

    private const SECRET = 'rahasia-webhook';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        Storage::fake();
        config([
            'services.helpdesk.api_token' => self::TOKEN,
            'services.helpdesk.webhook_url' => self::WEBHOOK_URL,
            'services.helpdesk.webhook_secret' => self::SECRET,
        ]);
        Http::fake([
            'whatsapp.test/*' => Http::response(['status' => true]),
            'helpdesk.test/*' => Http::response(['ok' => true]),
        ]);
    }

    private function api(): self
    {
        return $this->withToken(self::TOKEN)->withHeader('Accept', 'application/json');
    }

    /** @return array<string, mixed> */
    private function ticket(string $category = 'IK', array $answers = [], array $overrides = []): array
    {
        return [
            'idempotency_key' => 'hd-kunci-1',
            'helpdesk_ticket_number' => 'HD-2026-0001',
            'category_key' => $category,
            'title' => 'Permintaan IK gudang',
            'reporter' => [
                'nik' => '',
                'name' => 'Budi Pelapor',
                'phone' => '0812-3456-7890',
                'email' => 'budi@example.com',
                'unit' => 'Warehouse',
                'job_title' => 'Leader',
                'business_entity' => 'TEST AREA',
            ],
            'answers' => [
                'email' => 'Budi@Example.com',
                'divisi' => 'Gudang',
                'departemen' => 'Warehouse',
                'jabatan' => 'Leader',
                'badan_usaha' => 'TEST AREA',
                'proses' => [
                    ['judul' => 'Penerimaan barang', 'tujuan' => 'Supaya penerimaan barang seragam.', 'prioritas' => 'High'],
                    ['judul' => 'Retur barang', 'tujuan' => 'Alur retur sering berbeda.', 'prioritas' => 'Urgent'],
                ],
                'obs_tanggal_1' => '2026-11-20',
                ...$answers,
            ],
            ...$overrides,
        ];
    }

    private function submit(array $payload): TestResponse
    {
        return $this->api()->post('/api/helpdesk/v1/tickets', $payload);
    }

    private function leader(): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => 'LEADER OD']);
        $user->setOdReportAccess(User::OD_LEADER);

        return $user->fresh();
    }

    private function jobdeskWithTemplate(): DocumentCategory
    {
        $jd = DocumentCategory::where('code', 'JD')->firstOrFail();
        $jd->forceFill(['template_path' => 'kategori-dokumen/template/jd.docx', 'template_name' => 'Template JD.docx'])->save();
        Storage::put('kategori-dokumen/template/jd.docx', 'template');

        return $jd;
    }

    /** @return list<HttpRequest> */
    private function webhooks(): array
    {
        return Http::recorded()->map(fn (array $pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => $r->url() === self::WEBHOOK_URL)->values()->all();
    }

    private function requesterMessages(): int
    {
        return Http::recorded()->filter(fn (array $pair) => str_contains($pair[0]->url(), 'whatsapp.test')
            && ($pair[0]->data()['recipient']['value'] ?? null) === '081234567890')->count();
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    private static function find(array $data, string $key): array
    {
        return collect($data)->firstWhere('key', $key) ?? [];
    }

    public function test_every_endpoint_requires_the_bearer_token(): void
    {
        $code = DocumentRequest::generateCode();
        foreach (['/api/helpdesk/v1/categories', '/api/helpdesk/v1/forms/IK', "/api/helpdesk/v1/tickets/{$code}"] as $url) {
            $this->getJson($url)->assertUnauthorized()->assertJsonPath('message', 'Token integrasi tidak valid.');
            $this->withToken('salah')->getJson($url)->assertUnauthorized();
        }
        $this->postJson('/api/helpdesk/v1/tickets', $this->ticket())->assertUnauthorized();

        // Token belum diatur: semua panggilan ditolak, juga yang mengirim token kosong.
        config(['services.helpdesk.api_token' => null]);
        $this->withToken('')->getJson('/api/helpdesk/v1/categories')->assertUnauthorized();
        $this->withToken(self::TOKEN)->getJson('/api/helpdesk/v1/categories')->assertUnauthorized();
        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_categories_lists_active_document_categories(): void
    {
        DocumentCategory::where('code', 'SOP')->update(['is_active' => false]);

        $data = $this->api()->get('/api/helpdesk/v1/categories')->assertOk()->json('data');

        $this->assertContains(['key' => 'IK', 'name' => DocumentCategory::where('code', 'IK')->value('name'), 'active' => true], $data);
        $this->assertNotContains('SOP', array_column($data, 'key'));
        $this->assertSame(DocumentCategory::active()->count(), count($data));
    }

    public function test_form_for_an_observation_category(): void
    {
        $form = $this->api()->get('/api/helpdesk/v1/forms/IK')->assertOk()->json('data');

        $this->assertSame('IK', $form['key']);
        $this->assertNotEmpty($form['downloads']);
        $this->assertStringContainsString('contoh-ik.pdf', $form['downloads'][0]['url']);

        $fields = $form['fields'];
        // Nama dan WhatsApp diambil dari identitas pelapor, bukan field form.
        $this->assertSame(['email', 'divisi', 'departemen', 'jabatan', 'atasan', 'badan_usaha', 'proses', 'obs_info', 'obs_tanggal_1', 'obs_tanggal_2', 'obs_catatan'], array_column($fields, 'key'));
        $this->assertSame('reporter.email', self::find($fields, 'email')['prefill']);
        $this->assertSame('reporter.unit', self::find($fields, 'divisi')['prefill']);
        $this->assertSame('reporter.job_title', self::find($fields, 'jabatan')['prefill']);
        $this->assertSame('reporter.business_entity', self::find($fields, 'badan_usaha')['prefill']);
        $this->assertSame(DocumentRequest::DEPARTMENTS, array_column(self::find($fields, 'departemen')['options'], 'value'));
        $this->assertSame(['TEST AREA'], array_column(self::find($fields, 'badan_usaha')['options'], 'value'));
        $this->assertFalse(self::find($fields, 'atasan')['required']);
        $this->assertTrue(self::find($fields, 'obs_tanggal_1')['required']);
        $this->assertSame(['min_date' => 'today'], self::find($fields, 'obs_tanggal_1')['rules']);

        $proses = self::find($fields, 'proses');
        $this->assertSame('repeater', $proses['type']);
        $this->assertSame([1, DocumentRequest::MAX_PROCESSES], [$proses['min_items'], $proses['max_items']]);
        $this->assertSame(['judul', 'prioritas', 'tujuan', 'sistem', 'lampiran'], array_column($proses['fields'], 'key'));
        $this->assertSame(OdDocument::PRIORITAS, array_column(self::find($proses['fields'], 'prioritas')['options'], 'value'));
        $this->assertSame([
            'accept' => OdDocumentAttachment::LAMPIRAN_EXTENSIONS,
            'max_kb' => OdDocumentAttachment::MAX_KB,
            'max_files' => OdDocumentAttachment::MAX_FILES,
            'max_total_kb' => OdDocumentAttachment::MAX_KB,
        ], self::find($proses['fields'], 'lampiran')['rules']);
        $this->assertSame(['min' => 10, 'max' => 2000], self::find($proses['fields'], 'tujuan')['rules']);
    }

    public function test_form_for_a_normal_and_a_template_category(): void
    {
        $fields = $this->api()->get('/api/helpdesk/v1/forms/SOP')->assertOk()->json('data.fields');
        $this->assertNotContains('obs_tanggal_1', array_column($fields, 'key'));
        $this->assertSame(['judul', 'prioritas', 'tujuan', 'sistem', 'lampiran'], array_column(self::find($fields, 'proses')['fields'], 'key'));

        $jd = $this->jobdeskWithTemplate();
        $form = $this->api()->get('/api/helpdesk/v1/forms/JD')->assertOk()->json('data');
        $proses = self::find($form['fields'], 'proses')['fields'];
        $this->assertSame(['judul', 'prioritas', 'jenis_pengajuan', 'template'], array_column($proses, 'key'));
        $this->assertSame(OdDocument::JENIS_PENGAJUAN, array_column(self::find($proses, 'jenis_pengajuan')['options'], 'value'));
        $this->assertSame(['accept' => OdDocumentAttachment::TEMPLATE_EXTENSIONS, 'max_kb' => OdDocumentAttachment::MAX_KB], self::find($proses, 'template')['rules']);
        $this->assertContains(route('document-request.template', $jd), array_column($form['downloads'], 'url'));
    }

    public function test_unknown_or_inactive_category_form_is_not_found(): void
    {
        $this->api()->get('/api/helpdesk/v1/forms/TIDAKADA')->assertNotFound()->assertJsonStructure(['message']);

        DocumentCategory::where('code', 'IK')->update(['is_active' => false]);
        $this->api()->get('/api/helpdesk/v1/forms/IK')->assertNotFound();
    }

    public function test_submit_creates_a_helpdesk_request_for_the_matching_employee_and_replays_idempotently(): void
    {
        $employee = User::factory()->staff()->create(['employee_id' => 'NIK-001']);

        $response = $this->submit($this->ticket(overrides: ['reporter' => [...$this->ticket()['reporter'], 'nik' => 'NIK-001']]))->assertCreated();

        $request = DocumentRequest::sole();
        $this->assertSame(DocumentRequest::SOURCE_HELPDESK, $request->source);
        $this->assertSame('HD-2026-0001', $request->helpdesk_ticket_number);
        $this->assertSame($employee->id, $request->user_id);
        $this->assertSame('Budi Pelapor', $request->nama);
        $this->assertSame('081234567890', $request->whatsapp);
        $this->assertSame('budi@example.com', $request->email);
        $this->assertSame('2026-11-20', $request->obs_tanggal_1->toDateString());
        $this->assertSame(['Penerimaan barang', 'Retur barang'], OdDocument::orderBy('sort')->pluck('title')->all());

        $response->assertJsonPath('data.ref', $request->code)
            ->assertJsonPath('data.status', ['code' => 'open', 'label' => 'Pengajuan diterima'])
            ->assertJsonPath('data.items.0.title', 'Penerimaan barang')
            ->assertJsonPath('data.items.0.status.code', 'open')
            ->assertJsonPath('data.timeline.0.label', 'Pengajuan diterima')
            ->assertJsonStructure(['data' => ['ref', 'url', 'status', 'summary', 'items', 'timeline', 'comments', 'files', 'updated_at']]);

        // Pemohon dikabari helpdesk, bukan aplikasi ini (WhatsApp maupun notifikasi akun).
        $this->assertSame(0, $this->requesterMessages());
        $this->assertSame(0, $employee->notifications()->count());
        $this->assertSame([], $this->webhooks());

        // Kiriman ulang dengan kunci sama (meski isiannya kini tidak valid) mengembalikan tiket yang sama.
        $this->submit($this->ticket(answers: ['proses' => []]))->assertOk()->assertJsonPath('data.ref', $request->code);
        $this->assertSame(1, DocumentRequest::count());
    }

    public function test_submit_without_a_known_nik_is_a_guest_request(): void
    {
        $this->submit($this->ticket(overrides: ['reporter' => [...$this->ticket()['reporter'], 'nik' => 'NIK-TIDAK-ADA']]))->assertCreated();
        $this->assertNull(DocumentRequest::sole()->user_id);
    }

    public function test_validation_errors_use_the_helpdesk_field_paths(): void
    {
        $this->submit($this->ticket(
            answers: ['proses' => [['judul' => '', 'tujuan' => 'pendek', 'prioritas' => 'Super']], 'obs_tanggal_1' => '2026-11-17', 'departemen' => 'Lain'],
            overrides: ['reporter' => ['name' => 'Budi', 'phone' => '12']],
        ))->assertUnprocessable()->assertJsonValidationErrors([
            'answers.proses.0.judul', 'answers.proses.0.tujuan', 'answers.proses.0.prioritas', 'answers.obs_tanggal_1',
            'answers.departemen', 'reporter.phone',
        ]);

        $this->submit($this->ticket(answers: ['proses' => [['judul' => str_repeat('a', 201), 'tujuan' => 'Uraian cukup panjang.', 'prioritas' => 'Low']]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['answers.proses.0.judul']);

        $this->submit($this->ticket(overrides: ['category_key' => 'TIDAKADA']))->assertUnprocessable()->assertJsonValidationErrors(['category_key']);
        $this->submit($this->ticket(overrides: ['idempotency_key' => '']))->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key']);

        // Kategori tanpa observasi: tanggal yang ikut terkirim diabaikan.
        $this->submit($this->ticket('SOP', ['obs_tanggal_1' => '2026-11-01']))->assertCreated();
        $this->assertNull(DocumentRequest::sole()->obs_tanggal_1);
    }

    public function test_submit_with_attachments_and_template_files(): void
    {
        $this->submit($this->ticket('SOP', ['proses' => [[
            'judul' => 'Penerimaan barang', 'tujuan' => 'Supaya penerimaan barang seragam.', 'prioritas' => 'High',
            'lampiran' => [UploadedFile::fake()->create('alur.pdf', 100), UploadedFile::fake()->create('data.xlsx', 50)],
        ]]]))->assertCreated();
        $this->assertSame(['alur.pdf', 'data.xlsx'], OdDocumentAttachment::orderBy('id')->pluck('name')->all());

        $this->submit($this->ticket('SOP', ['proses' => [[
            'judul' => 'Retur', 'tujuan' => 'Alur retur sering berbeda.', 'prioritas' => 'High',
            'lampiran' => [UploadedFile::fake()->create('virus.exe', 10)],
        ]]], ['idempotency_key' => 'hd-kunci-2']))->assertUnprocessable()->assertJsonValidationErrors(['answers.proses.0.lampiran.0']);

        $this->jobdeskWithTemplate();
        $this->submit($this->ticket('JD', ['proses' => [[
            'judul' => 'Jobdesk Admin Gudang', 'prioritas' => 'Medium', 'jenis_pengajuan' => 'Baru',
            'template' => UploadedFile::fake()->create('jd-terisi.docx', 30),
        ]]], ['idempotency_key' => 'hd-kunci-3']))->assertCreated();
        $attachment = OdDocumentAttachment::latest('id')->firstOrFail();
        $this->assertSame([OdDocumentAttachment::KIND_TEMPLATE, 'jd-terisi.docx'], [$attachment->kind, $attachment->name]);
        Storage::assertExists($attachment->path);

        $this->submit($this->ticket('JD', ['proses' => [['judul' => 'Jobdesk', 'prioritas' => 'Medium', 'jenis_pengajuan' => 'Baru']]], ['idempotency_key' => 'hd-kunci-4']))
            ->assertUnprocessable()->assertJsonValidationErrors(['answers.proses.0.template']);
    }

    public function test_snapshot_maps_item_statuses_to_the_ticket_status(): void
    {
        $code = $this->submit($this->ticket())->assertCreated()->json('data.ref');
        [$first, $second] = OdDocument::orderBy('sort')->get()->all();
        $show = fn () => $this->api()->get("/api/helpdesk/v1/tickets/{$code}")->assertOk();

        $show()->assertJsonPath('data.status.code', 'open');

        $first->update(['stage' => OdDocument::STAGE_PROGRESS]);
        $show()->assertJsonPath('data.status', ['code' => 'in_progress', 'label' => 'On Progress'])
            ->assertJsonPath('data.items.0.status.code', 'in_progress');

        // Satu selesai (dengan file), satu batal → selesai, file rilis dikirim sebagai link bertanda tangan.
        $first->forceFill(['file_path' => 'od-dokumen/ik.pdf', 'file_name' => 'IK-Final.pdf'])->save();
        Storage::put('od-dokumen/ik.pdf', 'pdf');
        $first->applyStage(OdDocument::STAGE_DONE);
        $first->save();
        $second->applyStatus(OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_CANCEL));
        $second->save();
        $snapshot = $show()->assertJsonPath('data.status.code', 'done')
            ->assertJsonPath('data.items.1.status', ['code' => 'cancelled', 'label' => OdDocument::STAGE_CANCELLED])
            ->assertJsonPath('data.files.0.name', 'IK-Final.pdf')->json('data');
        // Link bertanda tangan bisa dibuka (disk S3/fake mengalihkan ke URL sementara).
        $this->assertContains($this->get($snapshot['files'][0]['url'])->status(), [200, 302]);
        $this->get(route('document-request.file', $first))->assertForbidden();

        $first->applyStatus(OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_CANCEL));
        $first->save();
        $show()->assertJsonPath('data.status.code', 'cancelled')->assertJsonPath('data.files', []);
    }

    public function test_only_helpdesk_requests_are_readable(): void
    {
        $this->post(route('document-request.store'), [
            'kategori' => DocumentCategory::where('code', 'SOP')->value('id'), 'nama' => 'Budi', 'divisi' => 'Gudang',
            'departemen' => 'Warehouse', 'jabatan' => 'Leader', 'whatsapp' => '081234567890', 'email' => 'budi@example.com',
            'badan_usaha' => 'TEST AREA', 'proses' => [['judul' => 'Retur', 'tujuan' => 'Alur retur sering berbeda.', 'prioritas' => 'Low']],
        ])->assertSessionHasNoErrors();

        $web = DocumentRequest::sole();
        $this->assertSame(DocumentRequest::SOURCE_WEB, $web->source);
        // Form web tetap mengabari pemohonnya.
        $this->assertSame(1, $this->requesterMessages());

        $this->api()->get("/api/helpdesk/v1/tickets/{$web->code}")->assertNotFound();
        $this->api()->get('/api/helpdesk/v1/tickets/IK-20261118-XXXX')->assertNotFound();
    }

    public function test_od_changes_send_a_signed_webhook_and_no_requester_message(): void
    {
        $code = $this->submit($this->ticket())->assertCreated()->json('data.ref');
        $request = DocumentRequest::sole();
        $leader = $this->leader();
        $first = $request->processes()->first();

        Carbon::setTestNow('2026-11-18 11:00:00');
        $this->actingAs($leader)->put(route('od-report.requests.update', $request), [
            'updated_at' => $request->fresh()->updated_at->toIso8601String(),
            'proses' => [$first->id => ['stage' => OdDocument::STAGE_PROGRESS]],
        ])->assertSessionHasNoErrors();

        $hooks = $this->webhooks();
        $this->assertCount(1, $hooks);
        $hook = $hooks[0];
        $timestamp = $hook->header('X-Helpdesk-Timestamp')[0];
        $this->assertSame((string) now()->getTimestamp(), $timestamp);
        $this->assertSame('sha256='.hash_hmac('sha256', "{$timestamp}.{$hook->body()}", self::SECRET), $hook->header('X-Helpdesk-Signature')[0]);
        $body = json_decode($hook->body(), true);
        $this->assertSame('ticket.updated', $body['type']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $body['event_id']);
        $this->assertSame($code, $body['data']['ref']);
        $this->assertSame('in_progress', $body['data']['status']['code']);
        $this->assertSame('2026-11-18T11:00:00+07:00', $body['data']['updated_at']);

        $this->actingAs($leader)->post(route('od-report.requests.comment', $request), ['body' => 'Mohon kirim contoh form.'])->assertSessionHasNoErrors();
        $hooks = $this->webhooks();
        $this->assertCount(2, $hooks);
        $comment = json_decode($hooks[1]->body(), true)['data']['comments'][0];
        $this->assertSame(['LEADER OD', 'Mohon kirim contoh form.'], [$comment['author'], $comment['body']]);
        $this->assertNotSame($body['event_id'], json_decode($hooks[1]->body(), true)['event_id']);

        // Perubahan status dari Approval Dokumen juga terkirim.
        $this->actingAs($leader)->patch(route('od-report.documents.status', $first), [
            'od_document_status_id' => OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_CANCEL)->id,
        ])->assertSessionHasNoErrors();
        $this->assertCount(3, $this->webhooks());
        $this->assertSame('cancelled', json_decode($this->webhooks()[2]->body(), true)['data']['items'][0]['status']['code']);

        $this->assertSame(0, $this->requesterMessages());
    }

    public function test_webhook_failures_are_retried_and_skipped_when_not_configured(): void
    {
        $this->submit($this->ticket())->assertCreated();
        $request = DocumentRequest::sole();
        $leader = $this->leader();

        $job = new SendHelpdeskWebhook($request->id);
        $this->assertGreaterThan(1, $job->tries);
        $this->assertNotEmpty($job->backoff());

        // Helpdesk menjawab non-2xx: job gagal supaya antrean mengulang dengan jeda.
        config(['services.helpdesk.webhook_url' => 'https://helpdesk-down.test/events']);
        Http::fake(['helpdesk-down.test/*' => Http::response(['message' => 'error'], 500)]);
        try {
            $job->handle();
            $this->fail('Webhook gagal harus dilempar agar diulang.');
        } catch (RequestException $e) {
            $this->assertSame(500, $e->response->status());
        }

        // Tanpa URL/rahasia: tidak ada kiriman.
        config(['services.helpdesk.webhook_url' => null]);
        $this->actingAs($leader)->post(route('od-report.requests.comment', $request), ['body' => 'Halo'])->assertSessionHasNoErrors();
        $this->assertSame([], $this->webhooks());
        (new SendHelpdeskWebhook($request->id))->handle();
        $this->assertSame([], $this->webhooks());
    }

    public function test_title_edits_and_deletion_are_sent_to_helpdesk(): void
    {
        $this->submit($this->ticket())->assertCreated();
        $request = DocumentRequest::sole();
        $leader = $this->leader();
        $first = $request->processes()->with('status')->first();

        $this->actingAs($leader)->put(route('od-report.documents.update', $first), [
            'title' => 'IK Penerimaan Barang Gudang', 'jenis' => 'IK', 'badan_usaha' => 'TEST AREA',
            'od_document_status_id' => $first->od_document_status_id, 'stage' => $first->stage,
        ])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->webhooks());
        $this->assertSame('IK Penerimaan Barang Gudang', json_decode($this->webhooks()[0]->body(), true)['data']['items'][0]['title']);

        $this->actingAs($leader)->delete(route('od-report.requests.destroy', $request))->assertSessionHasNoErrors();
        $last = json_decode($this->webhooks()[1]->body(), true)['data'];
        $this->assertSame(['code' => 'cancelled', 'label' => 'Dihapus'], $last['status']);
        // "Muat ulang" tetap bisa memulihkan pembatalan bila webhook terakhir gagal terkirim.
        $this->api()->get("/api/helpdesk/v1/tickets/{$request->code}")->assertOk()
            ->assertJsonPath('data.ref', $request->code)
            ->assertJsonPath('data.status', ['code' => 'cancelled', 'label' => 'Dihapus'])
            ->assertJsonPath('data.items.0.title', 'IK Penerimaan Barang Gudang');
    }

    public function test_replaying_the_key_of_a_deleted_request_returns_the_cancelled_ticket(): void
    {
        $code = $this->submit($this->ticket())->assertCreated()->json('data.ref');
        $this->actingAs($this->leader())->delete(route('od-report.requests.destroy', DocumentRequest::sole()))->assertSessionHasNoErrors();

        // Tanggal observasi sudah lewat saat helpdesk mengulang: tetap tiket yang sama, tanpa validasi ulang.
        Carbon::setTestNow('2026-11-25 10:00:00');
        $this->submit($this->ticket())->assertOk()
            ->assertJsonPath('data.ref', $code)
            ->assertJsonPath('data.status', ['code' => 'cancelled', 'label' => 'Dihapus']);
        $this->assertSame(1, DocumentRequest::withTrashed()->count());
    }

    public function test_api_attachment_total_per_document_is_limited(): void
    {
        $this->submit($this->ticket('SOP', ['proses' => [[
            'judul' => 'Penerimaan barang', 'tujuan' => 'Supaya penerimaan barang seragam.', 'prioritas' => 'High',
            'lampiran' => [UploadedFile::fake()->create('alur.pdf', 6000), UploadedFile::fake()->create('data.pdf', 6000)],
        ]]]))->assertUnprocessable()->assertJsonValidationErrors(['answers.proses.0.lampiran']);

        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_od_managers_are_told_about_helpdesk_requests(): void
    {
        DocumentRequestRecipient::create(['name' => 'Admin OD', 'whatsapp' => '6281111111111']);

        $code = $this->submit($this->ticket())->assertCreated()->json('data.ref');

        $this->assertTrue(Http::recorded()->contains(fn (array $pair) => str_contains($pair[0]->url(), 'whatsapp.test')
            && ($pair[0]->data()['recipient']['value'] ?? null) === '081111111111'
            && str_contains($pair[0]->data()['message']['text'] ?? '', "Pengajuan Dokumen Baru {$code}")));
        $this->assertSame(0, $this->requesterMessages());
    }

    public function test_document_edits_with_a_stage_change_send_one_webhook(): void
    {
        $this->submit($this->ticket())->assertCreated();
        $first = DocumentRequest::sole()->processes()->first();

        $this->actingAs($this->leader())->put(route('od-report.documents.update', $first), [
            'title' => $first->title, 'jenis' => 'IK', 'badan_usaha' => 'TEST AREA',
            'od_document_status_id' => $first->od_document_status_id, 'stage' => OdDocument::STAGE_PROGRESS,
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, $this->webhooks());
        $this->assertSame('in_progress', json_decode($this->webhooks()[0]->body(), true)['data']['items'][0]['status']['code']);
    }

    public function test_release_file_replacement_without_stage_change_sends_one_webhook(): void
    {
        $this->submit($this->ticket())->assertCreated();
        $first = DocumentRequest::sole()->processes()->first();
        $leader = $this->leader();
        $done = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE);

        $this->actingAs($leader)->patch(route('od-report.documents.status', $first), [
            'od_document_status_id' => $done->id, 'file' => UploadedFile::fake()->create('IK-Final.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->webhooks());

        // Status sama, hanya file rilis diganti: satu webhook dengan link file terbaru.
        $this->actingAs($leader)->patch(route('od-report.documents.status', $first), [
            'od_document_status_id' => $done->id, 'file' => UploadedFile::fake()->create('IK-Revisi.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $hooks = $this->webhooks();
        $this->assertCount(2, $hooks);
        $files = json_decode($hooks[1]->body(), true)['data']['files'];
        $this->assertSame(['IK-Revisi.pdf'], array_column($files, 'name'));
    }

    public function test_webhooks_for_the_same_request_never_run_concurrently(): void
    {
        $job = new SendHelpdeskWebhook(42);
        $middleware = collect($job->middleware())->first(fn ($m) => $m instanceof WithoutOverlapping);
        $this->assertNotNull($middleware);
        $this->assertNotNull($middleware->releaseAfter);

        // Kiriman lain untuk pengajuan yang sama sedang berjalan: job ini tidak dijalankan (dilepas kembali ke antrean).
        $lock = Cache::lock($middleware->getLockKey($job), 60);
        $this->assertTrue($lock->get());
        $ran = false;
        $middleware->handle($job, function () use (&$ran) {
            $ran = true;
        });
        $this->assertFalse($ran);

        // Pengajuan lain tidak tertahan.
        $other = new SendHelpdeskWebhook(43);
        collect($other->middleware())->first(fn ($m) => $m instanceof WithoutOverlapping)->handle($other, function () use (&$ran) {
            $ran = true;
        });
        $this->assertTrue($ran);
        $lock->release();
    }

    public function test_failed_token_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->withToken('token-salah')->getJson('/api/helpdesk/v1/categories')->assertUnauthorized();
        }

        $this->withToken('token-salah')->getJson('/api/helpdesk/v1/categories')->assertTooManyRequests();
    }

    public function test_web_requests_never_trigger_the_webhook(): void
    {
        $this->post(route('document-request.store'), [
            'kategori' => DocumentCategory::where('code', 'SOP')->value('id'), 'nama' => 'Budi', 'divisi' => 'Gudang',
            'departemen' => 'Warehouse', 'jabatan' => 'Leader', 'whatsapp' => '081234567890', 'email' => 'budi@example.com',
            'badan_usaha' => 'TEST AREA', 'proses' => [['judul' => 'Retur', 'tujuan' => 'Alur retur sering berbeda.', 'prioritas' => 'Low']],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->leader())->post(route('od-report.requests.comment', DocumentRequest::sole()), ['body' => 'Halo'])->assertSessionHasNoErrors();

        $this->assertSame([], $this->webhooks());
        $this->assertSame(2, $this->requesterMessages());
    }
}
