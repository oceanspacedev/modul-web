<?php

namespace Tests\Feature;

use App\Models\DocumentCategory;
use App\Models\DocumentDraft;
use App\Models\DocumentRequest;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Dokumen Builder dari Permintaan Dokumen: data pengajuan mengisi form (tanpa langsung menjalankan AI), ikut dikirim ke
 * AI sebagai konteks, dan satu item permintaan hanya punya satu draf untuk tiap penyusun yang boleh mengelolanya.
 */
class DocumentBuilderFromRequestTest extends TestCase
{
    private function member(string $access = User::OD_STAFF): User
    {
        $user = User::factory()->staff()->create();
        $user->setOdReportAccess($access);

        return $user->fresh();
    }

    private function process(User $pic, string $code = 'SOP', array $attributes = []): OdDocument
    {
        $category = DocumentCategory::where('code', $code)->firstOrFail();

        $request = DocumentRequest::create([
            'code' => $code.'-20261120-AB12',
            'document_category_id' => $category->id,
            'nama' => 'Budi Santoso',
            'divisi' => 'Operasional',
            'departemen' => 'Warehouse',
            'jabatan' => 'Leader',
            'atasan' => 'Pak Andi',
            'whatsapp' => '081234567890',
            'email' => 'budi@example.test',
            'tanggal' => today(),
            'catatan_admin' => 'Fokus ke shift malam',
        ]);

        return OdDocument::create([
            'title' => 'SOP Penerimaan Barang Gudang',
            'jenis' => $code,
            'badan_usaha' => 'PT SMI',
            'od_document_status_id' => OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN)->id,
            'pic_user_id' => $pic->id,
            'submitted_at' => today(),
            'month' => today()->format('Y-m'),
            'document_request_id' => $request->id,
            'tujuan' => 'Barang masuk tercatat rapi dan cepat',
            'prioritas' => 'High',
            'sistem' => 'WMS, scanner barcode',
            ...$attributes,
        ]);
    }

    public function test_the_pic_sees_their_request_items_and_the_form_is_prefilled_without_running_ai(): void
    {
        $pic = $this->member();
        $process = $this->process($pic);
        $otherProcess = $this->process($this->member(), 'IK', ['title' => 'IK Milik Orang Lain']);

        $this->actingAs($pic)->get(route('document-builder.create'))
            ->assertOk()
            ->assertSee('Dari Permintaan Dokumen')
            ->assertSee('SOP Penerimaan Barang Gudang')
            ->assertSee('SOP-20261120-AB12')
            ->assertDontSee('IK Milik Orang Lain');

        $this->actingAs($pic)->get(route('document-builder.form', ['category' => $process->category, 'permintaan' => $process->id]))
            ->assertOk()
            ->assertSee('Dari permintaan')
            ->assertSee('value="SOP Penerimaan Barang Gudang"', false)
            ->assertSee('value="Warehouse"', false)
            ->assertSee('Barang masuk tercatat rapi dan cepat</textarea>', false)
            ->assertSee('WMS, scanner barcode</textarea>', false)
            ->assertSee('value="PT SMI"', false)
            ->assertSee('name="permintaan" value="'.$process->id.'"', false);

        $this->assertDatabaseCount('document_drafts', 0);
        Http::assertNothingSent();

        $this->actingAs($pic)->get(route('document-builder.form', ['category' => $otherProcess->category, 'permintaan' => $otherProcess->id]))->assertRedirect();
    }

    public function test_the_draft_is_linked_and_the_request_data_reaches_the_ai(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['objective' => 'Tujuan dari AI'])]]]]]])]);

        $pic = $this->member();
        $process = $this->process($pic);

        $this->actingAs($pic)->post(route('document-builder.store', $process->category), [
            'mode' => 'ai',
            'permintaan' => $process->id,
            'company' => 'PT SMI',
            'answers' => ['title' => 'SOP Penerimaan Barang Gudang', 'department' => 'Warehouse', 'objective' => 'Barang masuk tercatat rapi'],
        ])->assertRedirect();

        $draft = DocumentDraft::sole();
        $this->assertSame($process->id, $draft->od_document_id);
        Http::assertSent(fn (Request $request) => str_contains($prompt = $request['contents'][0]['parts'][0]['text'], 'Info dari Permintaan Dokumen')
            && str_contains($prompt, 'Kode permintaan: SOP-20261120-AB12')
            && str_contains($prompt, 'Pemohon: Budi Santoso (Leader)')
            && str_contains($prompt, 'Prioritas: High')
            && str_contains($prompt, 'Catatan tim OD: Fokus ke shift malam'));

        $this->actingAs($pic)->get(route('document-builder.edit', $draft))->assertOk()->assertSee('SOP-20261120-AB12');
    }

    public function test_one_request_item_gets_only_one_draft(): void
    {
        $pic = $this->member();
        $process = $this->process($pic);
        $draft = DocumentDraft::create(['document_category_id' => $process->category->id, 'user_id' => $pic->id, 'od_document_id' => $process->id, 'title' => 'Draf pertama']);

        $this->actingAs($pic)->get(route('document-builder.form', ['category' => $process->category, 'permintaan' => $process->id]))
            ->assertRedirect(route('document-builder.edit', $draft));
        $this->actingAs($pic)->post(route('document-builder.store', $process->category), ['mode' => 'manual', 'permintaan' => $process->id, 'answers' => ['title' => 'Draf kedua']])
            ->assertRedirect(route('document-builder.edit', $draft));
        $this->assertDatabaseCount('document_drafts', 1);

        $this->actingAs($pic)->get(route('document-builder.create'))->assertSee('Lihat draf');
        $this->actingAs($pic)->get(route('od-report.requests.show', $process->documentRequest))->assertOk()->assertSee('Lihat draf builder');
    }

    public function test_request_detail_offers_the_builder_for_open_items_and_rejects_a_category_mismatch(): void
    {
        $pic = $this->member();
        $process = $this->process($pic);

        $this->actingAs($pic)->get(route('od-report.requests.show', $process->documentRequest))
            ->assertOk()
            ->assertSee('Susun di Builder')
            ->assertSee(route('document-builder.form', ['category' => $process->category, 'permintaan' => $process->id]), false);

        $jd = DocumentCategory::where('code', 'JD')->first();
        $this->actingAs($pic)->get(route('document-builder.form', ['category' => $jd, 'permintaan' => $process->id]))->assertNotFound();
    }

    public function test_prefill_follows_the_purpose_step_of_each_template(): void
    {
        $pic = $this->member();

        $jd = $this->process($pic, 'JD', ['title' => 'Admin Gudang']);
        $this->actingAs($pic)->get(route('document-builder.form', ['category' => $jd->category, 'permintaan' => $jd->id]))
            ->assertOk()
            ->assertSee('id="q_purpose" rows="3"', false)
            ->assertSee('Barang masuk tercatat rapi dan cepat</textarea>', false);
    }

    public function test_a_draft_made_by_another_user_does_not_lock_out_the_pic(): void
    {
        $pic = $this->member();
        $leader = $this->member(User::OD_LEADER);
        $process = $this->process($pic);

        // Leader menyusun item yang PIC-nya staff.
        $this->actingAs($leader)->post(route('document-builder.store', $process->category), [
            'mode' => 'manual', 'permintaan' => $process->id, 'answers' => ['title' => 'Draf Leader'],
        ])->assertRedirect();
        $leaderDraft = DocumentDraft::sole();

        // Draf itu hanya bisa dibuka pembuatnya, jadi PIC tidak diberi tautan ke sana dan tidak diarahkan ke sana.
        $formUrl = route('document-builder.form', ['category' => $process->category, 'permintaan' => $process->id]);

        $this->actingAs($pic)->get(route('document-builder.create'))->assertOk()
            ->assertDontSee('Lihat draf')
            ->assertDontSee(route('document-builder.edit', $leaderDraft), false)
            ->assertSee($formUrl, false);

        $this->actingAs($pic)->get(route('od-report.requests.show', $process->documentRequest))->assertOk()
            ->assertDontSee('Lihat draf builder')
            ->assertSee('Susun di Builder');

        // Halaman form memberi PIC drafnya sendiri, bukan bouncing ke draf orang lain.
        $this->actingAs($pic)->get($formUrl)->assertOk()->assertSee('Dari permintaan');

        $this->actingAs($pic)->post(route('document-builder.store', $process->category), [
            'mode' => 'manual', 'permintaan' => $process->id, 'answers' => ['title' => 'Draf PIC'],
        ])->assertSessionHasNoErrors();

        $picDraft = DocumentDraft::latest('id')->firstOrFail();
        $this->assertNotSame($leaderDraft->id, $picDraft->id);
        $this->assertSame($pic->id, $picDraft->user_id);
        $this->assertSame($process->id, $picDraft->od_document_id);
        $this->assertSame('Draf Leader', $leaderDraft->fresh()->title);
        $this->actingAs($pic)->get(route('document-builder.edit', $picDraft))->assertOk();

        // Leader masih melihat tautan draf seperti biasa.
        $this->actingAs($leader)->get(route('document-builder.create'))->assertSee('Lihat draf');
    }

    public function test_a_foreign_draft_stays_unmanaged_and_is_never_linked_to_the_pic(): void
    {
        $pic = $this->member();
        $leader = $this->member(User::OD_LEADER);
        $process = $this->process($pic);
        $draft = DocumentDraft::create([
            'document_category_id' => $process->category->id, 'user_id' => $leader->id, 'od_document_id' => $process->id, 'title' => 'Draf Leader',
        ]);

        // Aturan kepemilikan draf tidak dilonggarkan: hanya pembuatnya (atau Leader) yang mengelolanya.
        $this->assertTrue($draft->canBeManagedBy($leader));
        $this->assertFalse($draft->canBeManagedBy($pic));
        $this->actingAs($pic)->get(route('document-builder.edit', $draft))->assertRedirect();
        $this->actingAs($pic)->get(route('document-builder.show', $draft))->assertRedirect();
        $this->actingAs($pic)->delete(route('document-builder.destroy', $draft))->assertRedirect();
        $this->assertNotSoftDeleted($draft);

        // Karena tidak bisa dibuka, tautannya tidak pernah ditawarkan ke PIC.
        $this->actingAs($pic)->get(route('document-builder.create'))->assertDontSee(route('document-builder.edit', $draft), false);
        $this->actingAs($pic)->get(route('od-report.requests.show', $process->documentRequest))->assertDontSee(route('document-builder.edit', $draft), false);
    }

    public function test_form_origin_banner_adds_wrapping_classes_for_long_requester_metadata(): void
    {
        $pic = $this->member();
        $process = $this->process($pic);
        $process->documentRequest->update([
            'nama' => str_repeat('LongUnbrokenRequester', 4),
            'jabatan' => str_repeat('JabatanTanpaSpasi', 3),
            'departemen' => str_repeat('DepartemenPanjang', 3),
        ]);

        $this->actingAs($pic)
            ->get(route('document-builder.form', [$process->category, 'permintaan' => $process->id]))
            ->assertOk()
            ->assertSee('<div class="small min-w-0 text-break">', false)
            ->assertSee(str_repeat('LongUnbrokenRequester', 4));
    }
}
