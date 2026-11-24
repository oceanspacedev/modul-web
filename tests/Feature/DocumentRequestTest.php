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
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permintaan Dokumen: pengajuan publik/login, lacak, dan pemrosesan oleh tim OD yang terhubung ke Approval Dokumen.
 */
class DocumentRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        Http::fake(['whatsapp.test/*' => Http::response(['status' => true])]);
        Storage::fake();
    }

    private function member(string $access, string $name): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => $name]);
        $user->setOdReportAccess($access);

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'kategori' => DocumentCategory::where('code', 'IK')->value('id'),
            'nama' => 'Budi Pemohon',
            'divisi' => 'General Affair Aset',
            'departemen' => 'Warehouse',
            'jabatan' => 'Leader',
            'whatsapp' => '0812-3456-7890',
            'email' => 'Budi@Example.com',
            'badan_usaha' => 'TEST AREA',
            'proses' => [
                ['judul' => 'Penerimaan barang', 'tujuan' => 'Supaya penerimaan barang seragam di semua gudang.', 'prioritas' => 'High'],
                ['judul' => 'Retur barang', 'tujuan' => 'Alur retur sering berbeda antar cabang.', 'prioritas' => 'Urgent', 'sistem' => 'Accurate'],
            ],
            'obs_tanggal_1' => '2026-11-20',
            'idempotency_key' => 'kunci-uji-1',
            ...$overrides,
        ];
    }

    public function test_public_request_creates_ik_documents_for_the_default_pic(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF IK');
        DocumentCategory::where('code', 'IK')->update(['pic_user_id' => $staff->id]);

        $this->get(route('document-request.create'))->assertOk()->assertSee('Permintaan Dokumen')->assertSee('Instruksi Kerja');
        $response = $this->post(route('document-request.store'), $this->payload());

        $request = DocumentRequest::sole();
        $response->assertRedirect(route('document-request.track', ['kode' => $request->code]));
        $this->assertMatchesRegularExpression(DocumentRequest::CODE_PATTERN, $request->code);
        $this->assertStringStartsWith('IK-20261118-', $request->code);
        $this->assertSame('081234567890', $request->whatsapp);
        $this->assertSame('budi@example.com', $request->email);
        $this->assertNull($request->user_id);

        $documents = OdDocument::orderBy('sort')->get();
        $this->assertSame(['Penerimaan barang', 'Retur barang'], $documents->pluck('title')->all());
        $this->assertTrue($documents->every(fn ($d) => $d->jenis === 'IK' && $d->stage === 'Pengajuan diterima' && $d->pic_user_id === $staff->id));
        $this->assertSame('Urgent', $request->load('processes')->topPriority());

        // Pemohon dikabari lewat WhatsApp dengan link lacak.
        Http::assertSent(fn ($r) => str_contains($r->body(), $request->code));

        // Kiriman ganda dengan kunci yang sama tidak membuat tiket kedua.
        $this->post(route('document-request.store'), $this->payload());
        $this->assertSame(1, DocumentRequest::count());

        // Dokumen langsung muncul di Approval Dokumen PIC-nya.
        $this->actingAs($staff)->get(route('od-report.documents.index'))->assertSee('Retur barang')->assertSee($request->code);
    }

    public function test_request_validation_and_honeypot(): void
    {
        $this->post(route('document-request.store'), $this->payload([
            'whatsapp' => '12', 'obs_tanggal_1' => '2026-11-17', 'proses' => [['judul' => '', 'tujuan' => 'pendek', 'prioritas' => 'Super']],
        ]))->assertSessionHasErrors(['whatsapp', 'obs_tanggal_1', 'proses.0.judul', 'proses.0.tujuan', 'proses.0.prioritas']);

        $this->post(route('document-request.store'), $this->payload(['website' => 'http://spam.test']))->assertSessionHas('success');
        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_the_observation_schedule_is_only_validated_when_the_category_needs_it(): void
    {
        $sop = DocumentCategory::where('code', 'SOP')->value('id');

        // Kategori tanpa observasi menyembunyikan langkah jadwal: tanggal lama yang tertinggal di form tidak boleh
        // membuat pengajuan ditolak pada langkah yang tidak terlihat.
        $this->post(route('document-request.store'), $this->payload([
            'kategori' => $sop, 'obs_tanggal_1' => '2026-11-17', 'obs_tanggal_2' => '2026-11-17', 'obs_catatan' => 'Pagi',
        ]))->assertSessionHasNoErrors();

        $request = DocumentRequest::sole();
        $this->assertSame($sop, $request->document_category_id);
        $this->assertNull($request->obs_tanggal_1);
        $this->assertNull($request->obs_catatan);

        // Kategori dengan observasi (IK) tetap menuntut jadwal yang benar.
        $this->post(route('document-request.store'), $this->payload(['idempotency_key' => 'kunci-lalu', 'obs_tanggal_1' => '2026-11-17']))
            ->assertSessionHasErrors('obs_tanggal_1');
        $this->post(route('document-request.store'), $this->payload(['idempotency_key' => 'kunci-kosong', 'obs_tanggal_1' => null]))
            ->assertSessionHasErrors('obs_tanggal_1');
        $this->assertSame(1, DocumentRequest::count());

        $this->post(route('document-request.store'), $this->payload(['idempotency_key' => 'kunci-ik', 'obs_tanggal_2' => '2026-11-21']))
            ->assertSessionHasNoErrors();
        $ik = DocumentRequest::latest('id')->firstOrFail();
        $this->assertSame('2026-11-20', $ik->obs_tanggal_1->toDateString());
        $this->assertSame('2026-11-21', $ik->obs_tanggal_2->toDateString());
    }

    public function test_every_client_checked_field_has_a_visible_message_box_in_the_wizard(): void
    {
        $html = $this->get(route('document-request.create'))->assertOk()->getContent();

        // Aturan browser (required, type=email, minlength tujuan, radio prioritas wajib, min tanggal) berjalan tanpa
        // balasan server, jadi kotaknya harus ada sejak halaman pertama dimuat dan diisi oleh JavaScript wizard.
        foreach (['nama', 'divisi', 'departemen', 'jabatan', 'whatsapp', 'email', 'kategori', 'proses[0][judul]',
            'proses[0][tujuan]', 'proses[0][prioritas]', 'obs_tanggal_1', 'obs_tanggal_2'] as $field) {
            $this->assertStringContainsString('data-feedback="'.$field.'"', $html);
        }

        // Kesalahan dari server masuk ke kotak yang sama dan langsung terlihat, termasuk pada grup radio.
        $failed = $this->followingRedirects()->post(route('document-request.store'), $this->payload([
            'idempotency_key' => 'kunci-pesan',
            'email' => 'bukan-email',
            'proses' => [['judul' => 'Penerimaan barang', 'tujuan' => 'Penerimaan barang tercatat rapi.', 'prioritas' => '']],
        ]))->getContent();

        $this->assertMatchesRegularExpression('/<div class="invalid-feedback d-block" data-feedback="email">.+<\/div>/', $failed);
        $this->assertMatchesRegularExpression('/<div class="invalid-feedback d-block" data-feedback="proses\[0\]\[prioritas\]">.+<\/div>/', $failed);
        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_logged_in_requester_gets_prefilled_form_and_sees_own_requests(): void
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => 'SITI KARYAWAN', 'email' => 'siti@example.com']);

        $this->actingAs($user)->get(route('document-request.create'))->assertOk()->assertSee('value="SITI KARYAWAN"', false);
        $this->actingAs($user)->post(route('document-request.store'), $this->payload(['nama' => 'SITI KARYAWAN']));

        $request = DocumentRequest::sole();
        $this->assertSame($user->id, $request->user_id);
        $this->actingAs($user)->get(route('document-request.mine'))->assertOk()->assertSee($request->code)->assertSee('Penerimaan barang');
    }

    public function test_guest_fills_everything_and_logged_in_user_gets_saved_details_back(): void
    {
        // Tamu: form terbuka tanpa login dan semua kolom kosong; ada jalan masuk dari halaman login.
        $this->get(route('login'))->assertOk()->assertSee(route('document-request.create'));
        $this->get(route('document-request.create'))->assertOk()
            ->assertSee('id="nama" name="nama" maxlength="100" required autocomplete="name" value=""', false)->assertSee('Masuk');

        // Login: atasan dari approval line; departemen dan jabatan diingat dari pengajuan sebelumnya.
        $boss = User::factory()->staff()->create(['nama_lengkap' => 'PAK ATASAN']);
        $user = User::factory()->staff()->create(['nama_lengkap' => 'SITI KARYAWAN', 'approval_id' => $boss->id]);
        $this->actingAs($user)->get(route('document-request.create'))->assertOk()
            ->assertSee('value="SITI KARYAWAN"', false)->assertSee('value="PAK ATASAN"', false)
            ->assertDontSee('<option value="Finance" selected>', false);

        $this->actingAs($user)->post(route('document-request.store'), $this->payload(['nama' => 'SITI KARYAWAN', 'departemen' => 'Finance', 'jabatan' => 'Coordinator']));
        $this->assertSame(1, DocumentRequest::where('user_id', $user->id)->count());

        $this->actingAs($user)->get(route('document-request.create'))->assertOk()
            ->assertSee('<option value="Finance" selected>', false);
        $this->assertMatchesRegularExpression('/<option value="(Staff|Coordinator)" selected>/', $this->actingAs($user)->get(route('document-request.create'))->getContent());
    }

    public function test_the_wizard_falls_back_to_one_long_form_and_dedupes_on_a_server_made_key(): void
    {
        $html = $this->get(route('document-request.create'))->assertOk()->getContent();

        // Kunci idempotensi dibuat saat form ditampilkan, jadi kiriman ganda dari halaman yang sama benar-benar
        // ditolak service walaupun JavaScript tidak dipakai.
        $this->assertSame(1, preg_match('/name="idempotency_key" id="idempotencyKey" value="([^"]+)"/', $html, $matched));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $matched[1]);

        $this->post(route('document-request.store'), $this->payload(['idempotency_key' => $matched[1]]));
        $this->post(route('document-request.store'), $this->payload(['idempotency_key' => $matched[1]]));
        $this->assertSame(1, DocumentRequest::count());

        // Konvensi aplikasi: form ditandai .setup-wizard dan JavaScript menambahkan .is-enhanced.
        $this->assertStringContainsString('id="requestForm" class="card setup-wizard"', $html);
        $this->assertStringContainsString("form.classList.add('is-enhanced')", $html);

        // Tanpa JavaScript seluruh langkah tampil sekaligus dan tombol Lanjut/Kembali disembunyikan CSS.
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.setup-wizard:not(.is-enhanced) #prevStep', $css);
        $this->assertStringContainsString('.setup-wizard:not(.is-enhanced) #nextStep', $css);

        // Tombol kirim berada di footer, bukan di dalam salah satu langkah, jadi selalu terlihat tanpa JavaScript.
        $this->assertStringContainsString('id="submitRequest"', Str::of($html)->afterLast('card-footer')->before('</form>')->toString());

        // Tanggal observasi tidak dibuat wajib di markup: server yang tahu kategorinya butuh observasi atau tidak.
        $this->assertSame(1, preg_match('/<input type="date"[^>]*name="obs_tanggal_1"[^>]*>/', $html, $observation));
        $this->assertStringNotContainsString('required', $observation[0]);
    }

    public function test_tracking_shows_progress_but_never_internal_notes(): void
    {
        $this->post(route('document-request.store'), $this->payload());
        $request = DocumentRequest::sole();
        $request->update(['catatan_admin' => 'CATATAN RAHASIA TIM']);

        $this->get(route('document-request.track', ['kode' => strtolower($request->code)]))->assertOk()
            ->assertSee('Penerimaan barang')->assertSee('Pengajuan diterima')->assertSee('0812')
            ->assertDontSee('CATATAN RAHASIA TIM')->assertDontSee('081234567890')->assertDontSee('budi@example.com');

        $this->get(route('document-request.track', ['kode' => 'IK-20261118-ZZZZ']))->assertOk()->assertSee('tidak ditemukan');
    }

    public function test_od_team_moves_processes_through_stages_and_release_closes_the_document(): void
    {
        $leader = $this->member(User::OD_LEADER, 'LEADER OD');
        $this->post(route('document-request.store'), $this->payload());
        $request = DocumentRequest::sole();
        [$first, $second] = $request->processes()->get()->all();

        $this->actingAs($leader)->get(route('od-report.requests.index'))->assertOk()->assertSee($request->code);
        $this->actingAs($leader)->get(route('od-report.requests.show', $request))->assertOk()->assertSee('Retur barang');

        $this->actingAs($leader)->put(route('od-report.requests.update', $request), [
            'updated_at' => $request->fresh()->updated_at->toIso8601String(),
            'proses' => [
                $first->id => ['stage' => 'Selesai', 'prioritas' => 'High', 'pic_user_id' => $leader->id, 'file' => UploadedFile::fake()->create('ik-penerimaan.pdf', 100, 'application/pdf')],
                $second->id => ['stage' => 'On Progress', 'prioritas' => 'Medium'],
            ],
            'jadwal_observasi' => '2026-11-20',
            'catatan_admin' => 'Internal saja',
        ])->assertRedirect(route('od-report.requests.show', $request))->assertSessionHas('success');

        $first->refresh();
        $this->assertSame('Selesai', $first->stage);
        $this->assertTrue($first->status->isDone());
        $this->assertSame('2026-11-18', $first->received_at->toDateString());
        $this->assertSame($leader->id, $first->pic_user_id);
        $this->assertSame('Medium', $second->fresh()->prioritas);

        $request->refresh()->load('processes.status');
        $this->assertSame('On Progress', $request->stage());
        $this->assertSame('2026-11-20', $request->jadwal_observasi->toDateString());
        $this->assertSame(1, $request->releasedCount());
        $this->assertStringContainsString('Jadwal observasi', $request->histories()->pluck('note')->filter()->implode(' | '));

        // Satu pesan WhatsApp merangkum semua perubahan, lalu file dokumen yang dirilis dikirim sebagai dokumen
        // (selain pesan saat pengajuan dibuat).
        Http::assertSentCount(3);

        // Batal di Approval Dokumen terlihat pemohon sebagai Dibatalkan.
        $cancel = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_CANCEL);
        $this->actingAs($leader)->patch(route('od-report.documents.status', $second), ['od_document_status_id' => $cancel->id]);
        $this->get(route('document-request.track', ['kode' => $request->code]))->assertSee('Dibatalkan');

        // Status selesai di Approval Dokumen = status Selesai.
        $done = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE);
        $this->actingAs($leader)->patch(route('od-report.documents.status', $second), [
            'od_document_status_id' => $done->id, 'file' => UploadedFile::fake()->create('ik-retur.pdf', 100, 'application/pdf'),
        ]);
        $this->assertSame('Selesai', $second->fresh()->stage);
        $this->assertTrue($request->fresh()->load('processes.status')->isFinished());

        // Dokumen dari permintaan tidak bisa dihapus dari Approval Dokumen.
        $this->actingAs($leader)->delete(route('od-report.documents.destroy', $second))->assertSessionHas('error');
        $this->assertNotSoftDeleted($second);
    }

    public function test_stale_form_does_not_overwrite_and_staff_only_see_their_requests(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF OD');
        $other = $this->member(User::OD_STAFF, 'STAFF LAIN');
        DocumentCategory::where('code', 'IK')->update(['pic_user_id' => $staff->id]);
        $this->post(route('document-request.store'), $this->payload());
        $request = DocumentRequest::sole();

        $this->actingAs($other)->get(route('od-report.requests.index'))->assertDontSee($request->code);
        $this->actingAs($other)->get(route('od-report.requests.show', $request))->assertRedirect();

        $this->actingAs($staff)->put(route('od-report.requests.update', $request), [
            'updated_at' => '2020-01-01T00:00:00+07:00',
            'catatan_admin' => 'Tidak boleh tersimpan',
        ])->assertSessionHas('error');
        $this->assertNull($request->fresh()->catatan_admin);

        $this->actingAs($staff)->delete(route('od-report.requests.destroy', $request))->assertRedirect();
        $this->assertNotSoftDeleted($request);
    }
}
