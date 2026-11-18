<?php

namespace Tests\Feature;

use App\Models\OdActivity;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Daily, Weekly, Monthly dan Dashboard OD Report.
 */
class OdActivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rabu, 11 November 2026 (Week 2 November: 7–13 Nov).
        Carbon::setTestNow('2026-11-11 15:00:00');
    }

    private function member(string $access, string $name): User
    {
        $user = User::factory()->staff()->create(['nama_lengkap' => $name]);
        $user->setOdReportAccess($access);

        return $user->fresh();
    }

    private function activity(User $user, string $date, string $name, array $attributes = []): OdActivity
    {
        return OdActivity::create([
            'user_id' => $user->id,
            'date' => $date,
            'name' => $name,
            'result' => OdActivity::RESULT_DONE,
            'saved_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_activity_starts_as_draft_and_simpan_laporan_saves_it(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF OD');

        $this->actingAs($staff)->post(route('od-report.daily.store'), [
            'date' => '2026-11-11', 'name' => 'Review IK gudang', 'result' => 'On Progress', 'kendala' => 'Menunggu PIC',
        ])->assertRedirect(route('od-report.daily.index'));
        $this->actingAs($staff)->post(route('od-report.daily.store'), ['date' => '2026-11-11', 'name' => 'Drafting SOP', 'result' => 'Done']);

        $draft = OdActivity::where('name', 'Review IK gudang')->sole();
        $this->assertTrue($draft->isDraft());
        $this->assertSame(1, $draft->sort);

        $this->actingAs($staff)->get(route('od-report.daily.index'))->assertOk()->assertSee('Draft hari ini (2)');

        $this->actingAs($staff)->post(route('od-report.daily.save'))->assertSessionHas('success');
        $this->assertSame(0, OdActivity::whereNull('saved_at')->count());

        $this->actingAs($staff)->post(route('od-report.daily.reopen'));
        $this->assertSame(2, OdActivity::whereNull('saved_at')->count());
    }

    public function test_past_day_activity_is_saved_right_away_and_old_drafts_are_saved_on_open(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF OD');
        $old = $this->activity($staff, '2026-11-09', 'Draft kemarin', ['saved_at' => null]);

        $this->actingAs($staff)->post(route('od-report.daily.store'), ['date' => '2026-11-10', 'name' => 'Lupa input', 'result' => 'Done']);
        $this->assertFalse(OdActivity::where('name', 'Lupa input')->sole()->isDraft());

        $this->actingAs($staff)->post(route('od-report.daily.store'), ['date' => '2026-11-12', 'name' => 'Besok', 'result' => 'Done'])
            ->assertSessionHasErrors('date');

        $this->actingAs($staff)->get(route('od-report.daily.index'))->assertOk();
        $this->assertFalse($old->fresh()->isDraft());
    }

    public function test_drafts_can_be_reordered_and_staff_cannot_touch_others_activities(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF OD');
        $other = $this->member(User::OD_STAFF, 'STAFF LAIN');
        $first = $this->activity($staff, '2026-11-11', 'Satu', ['saved_at' => null, 'sort' => 1]);
        $second = $this->activity($staff, '2026-11-11', 'Dua', ['saved_at' => null, 'sort' => 2]);

        $this->actingAs($staff)->patch(route('od-report.daily.move', $second), ['direction' => 'up']);
        $this->assertSame([2, 1], [$first->fresh()->sort, $second->fresh()->sort]);

        $this->actingAs($other)->delete(route('od-report.daily.destroy', $first))->assertRedirect();
        $this->assertModelExists($first);
        $this->actingAs($other)->get(route('od-report.daily.edit', $first))->assertRedirect();
    }

    public function test_activity_cannot_link_a_document_the_user_cannot_see(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF OD');
        $other = $this->member(User::OD_STAFF, 'STAFF LAIN');
        $document = OdDocument::create([
            'title' => 'SOP rahasia', 'jenis' => 'SOP', 'pic_user_id' => $other->id, 'submitted_at' => today(), 'month' => '2026-11',
            'od_document_status_id' => OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_OPEN)->id,
        ]);

        $this->actingAs($staff)->post(route('od-report.daily.store'), [
            'date' => '2026-11-11', 'name' => 'Coba', 'result' => 'Done', 'od_document_id' => $document->id,
        ])->assertRedirect();
        $this->assertSame(0, OdActivity::count());
    }

    public function test_weekly_merges_the_same_job_in_a_week_and_staff_only_sees_their_own(): void
    {
        $staff = $this->member(User::OD_STAFF, 'STAFF OD');
        $other = $this->member(User::OD_STAFF, 'STAFF LAIN');
        $leader = $this->member(User::OD_LEADER, 'LEADER OD');
        $this->activity($staff, '2026-11-09', 'Drafting IK Retur', ['result' => 'On Progress', 'kendala' => 'Data belum lengkap']);
        $this->activity($staff, '2026-11-10', 'Drafting IK Retur', ['result' => 'Done']);
        $this->activity($staff, '2026-11-03', 'Drafting IK Retur', ['result' => 'On Progress']);
        $this->activity($other, '2026-11-10', 'Pekerjaan orang lain');

        $response = $this->actingAs($staff)->get(route('od-report.weekly', ['bulan' => '2026-11']))->assertOk();
        $response->assertSee('Week 2')->assertSee('Week 1')->assertSee('2x')->assertSee('Data belum lengkap')
            ->assertDontSee('Pekerjaan orang lain');

        $this->actingAs($leader)->get(route('od-report.weekly', ['bulan' => '2026-11']))->assertSee('Pekerjaan orang lain');
        $this->actingAs($leader)->get(route('od-report.weekly', ['bulan' => '2026-11', 'status' => 'Done']))
            ->assertSee('Drafting IK Retur')->assertDontSee('On Progress</span>', false);
    }

    public function test_leader_daily_text_is_the_team_afternoon_briefing(): void
    {
        $leader = $this->member(User::OD_LEADER, 'SEKAR AYUNING');
        $staff = $this->member(User::OD_STAFF, 'RIZAL HAMDI');
        $this->activity($leader, '2026-11-11', 'Review SOP', ['saved_at' => null]);
        $this->activity($staff, '2026-11-11', 'drafting ik retur');

        $this->actingAs($leader)->get(route('od-report.daily.index', ['teks' => 1]))->assertOk()
            ->assertSee('Afternoon Briefing BusDev')->assertSee('SEKAR')->assertSee('1. REVIEW SOP')->assertSee('1. DRAFTING IK RETUR');

        $this->actingAs($staff)->get(route('od-report.daily.index', ['teks' => 1]))
            ->assertSee('DAILY REPORT')->assertSee('drafting ik retur')->assertDontSee('REVIEW SOP');
    }

    public function test_dashboard_and_monthly_render_with_data(): void
    {
        $leader = $this->member(User::OD_LEADER, 'LEADER OD');
        $this->activity($leader, '2026-11-11', 'Review SOP');
        $this->activity($leader, '2026-11-02', 'Tahan dulu', ['result' => 'Hold']);
        OdDocument::create([
            'title' => 'SOP Penjualan', 'jenis' => 'SOP', 'pic_user_id' => $leader->id, 'submitted_at' => today(), 'month' => '2026-11',
            'od_document_status_id' => OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE)->id,
        ]);

        $this->actingAs($leader)->get(route('od-report.dashboard'))->assertOk()
            ->assertSee('Dashboard OD')->assertSee('1 pekerjaan berstatus Hold bulan ini');
        $this->actingAs($leader)->get(route('od-report.monthly', ['bulan' => '2026-11']))->assertOk()
            ->assertSee('SOP Penjualan')->assertSee('50%');
    }
}
