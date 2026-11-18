<?php

namespace Tests\Feature;

use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\OdPicDefault;
use App\Models\User;
use Tests\TestCase;

/**
 * Approval Dokumen OD: siapa melihat apa, PIC bawaan, dan aturan status.
 */
class OdDocumentTest extends TestCase
{
    private function member(string $access, string $name = 'Anggota OD'): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => $name]);
        $user->setOdReportAccess($access);

        return $user->fresh();
    }

    private function document(User $pic, array $attributes = []): OdDocument
    {
        return OdDocument::create([
            'title' => 'Dokumen '.$pic->nama_lengkap,
            'jenis' => 'SOP',
            'od_document_status_id' => OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN)->id,
            'pic_user_id' => $pic->id,
            'submitted_at' => today(),
            'month' => today()->format('Y-m'),
            ...$attributes,
        ]);
    }

    public function test_only_od_members_can_open_od_report(): void
    {
        $outsider = User::factory()->staff()->create();

        $this->actingAs($outsider)->get(route('od-report.documents.index'))->assertRedirect();
        $this->actingAs($this->member(User::OD_STAFF))->get(route('od-report.documents.index'))->assertOk()->assertSee('Approval Dokumen');
    }

    public function test_staff_only_sees_and_edits_own_documents_while_leader_sees_all(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF SATU');
        $other = $this->member(User::OD_STAFF, 'STAFF DUA');
        $leader = $this->member(User::OD_LEADER, 'LEADER OD');
        $mine = $this->document($staff);
        $theirs = $this->document($other);

        $this->actingAs($staff)->get(route('od-report.documents.index'))
            ->assertSee($mine->title)->assertDontSee($theirs->title);
        $this->actingAs($staff)->get(route('od-report.documents.edit', $theirs))->assertRedirect();

        $this->actingAs($leader)->get(route('od-report.documents.index'))
            ->assertSee($mine->title)->assertSee($theirs->title);
    }

    public function test_new_document_goes_to_default_pic_for_its_type_and_staff_always_owns_theirs(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF IK');
        $leader = $this->member(User::OD_LEADER, 'LEADER OD');
        OdPicDefault::create(['jenis' => 'IK', 'user_id' => $staff->id]);
        $open = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN);

        $this->actingAs($leader)->post(route('od-report.documents.store'), [
            'title' => 'IK Penerimaan Barang', 'jenis' => 'IK', 'od_document_status_id' => $open->id,
        ])->assertRedirect(route('od-report.documents.index'));
        $this->assertSame($staff->id, OdDocument::where('title', 'IK Penerimaan Barang')->value('pic_user_id'));

        $this->actingAs($staff)->post(route('od-report.documents.store'), [
            'title' => 'SOP Gudang', 'jenis' => 'SOP', 'pic_user_id' => $leader->id, 'od_document_status_id' => $open->id,
        ]);
        $document = OdDocument::where('title', 'SOP Gudang')->sole();
        $this->assertSame($staff->id, $document->pic_user_id);
        $this->assertSame('-', $document->badan_usaha);
        $this->assertSame(today()->format('Y-m'), $document->month);
    }

    public function test_done_status_sets_received_date_and_back_to_open_clears_it(): void
    {
        $staff = $this->member(User::OD_STAFF);
        $document = $this->document($staff);
        $done = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE);
        $open = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN);

        $this->actingAs($staff)->patch(route('od-report.documents.status', $document), ['od_document_status_id' => $done->id]);
        $this->assertSame(today()->toDateString(), $document->fresh()->received_at->toDateString());

        $this->actingAs($staff)->patch(route('od-report.documents.status', $document), ['od_document_status_id' => $open->id]);
        $this->assertNull($document->fresh()->received_at);
    }

    public function test_leader_manages_statuses_with_guards(): void
    {
        $leader = $this->member(User::OD_LEADER);
        $staff = $this->member(User::OD_STAFF);
        $this->actingAs($staff)->get(route('od-report.settings.index'))->assertRedirect();

        $this->actingAs($leader)->post(route('od-report.settings.statuses.store'), ['name' => 'Revisi', 'type' => 'open'])->assertSessionHas('success');

        $done = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE);
        $this->actingAs($leader)->put(route('od-report.settings.statuses.update', $done), ['name' => 'Rilis', 'type' => 'open'])->assertSessionHas('error');
        $this->actingAs($leader)->put(route('od-report.settings.statuses.update', $done), ['name' => 'Rilis', 'type' => 'done'])->assertSessionHas('success');
        $this->assertSame('Rilis', $done->fresh()->name);

        $this->document($staff, ['od_document_status_id' => $done->id]);
        $this->actingAs($leader)->delete(route('od-report.settings.statuses.destroy', $done))->assertSessionHas('error');
        $this->assertModelExists($done);

        $this->actingAs($leader)->put(route('od-report.settings.pics'), ['pic' => ['IK' => $staff->id, 'SOP' => $leader->id]])->assertSessionHas('success');
        $this->assertSame($staff->id, OdPicDefault::userFor('IK')->id);
    }

    public function test_plain_document_can_be_deleted(): void
    {
        $staff = $this->member(User::OD_STAFF);
        $plain = $this->document($staff);

        $this->actingAs($staff)->delete(route('od-report.documents.destroy', $plain))->assertSessionHas('success');
        $this->assertSoftDeleted($plain);
    }
}
