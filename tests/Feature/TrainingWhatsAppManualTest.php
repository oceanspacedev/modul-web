<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingWaLog;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Status undangan WhatsApp di daftar peserta dan kirim manual lewat WhatsApp Web saat WAGHub bermasalah.
 */
class TrainingWhatsAppManualTest extends TestCase
{
    private User $admin;

    private Training $training;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->training = Training::create([
            'title' => 'Product Knowledge Retail',
            'trainer_id' => $this->admin->id,
            'training_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'zoom_link' => 'https://zoom.us/j/123',
        ]);
    }

    private function participant(array $user, array $attributes = []): TrainingParticipant
    {
        $participant = TrainingParticipant::createFor($this->training, User::factory()->staff()->create($user)->id);
        $participant->update($attributes);

        return $participant->fresh();
    }

    public function test_failed_invitation_shows_the_reason_and_a_manual_whatsapp_web_link_with_the_same_text(): void
    {
        $failed = $this->participant(
            ['nama_lengkap' => 'BUDI GAGAL', 'no_hp' => '081234567890'],
            ['wa_status' => 'Gagal: Gateway 502 (Server WAGHub sedang sibuk/offline)'],
        );
        $sent = $this->participant(['nama_lengkap' => 'SARI TERKIRIM', 'no_hp' => '081234567891'], ['wa_status' => 'Terkirim', 'wa_sent_at' => now()]);

        $message = WhatsAppService::buildInvitationMessage($this->training->fresh(), $failed);
        $links = WhatsAppService::manualInvitationLinks($this->training->fresh(), $failed);
        $this->assertSame('https://web.whatsapp.com/send?phone=6281234567890&text='.rawurlencode($message), $links['web']);
        $this->assertSame('https://wa.me/6281234567890?text='.rawurlencode($message), $links['app']);
        $this->assertStringContainsString(rawurlencode(url('/training/portal/'.$failed->token)), $links['web']);

        // Tombol kirim manual di kolom WhatsApp hanya untuk yang gagal; menu aksi memuatnya untuk semua peserta.
        $html = $this->actingAs($this->admin)->get(route('training.show', $this->training))->getContent();
        $this->assertSame(2, substr_count($html, route('training.send-manual-wa', [$this->training, $failed])));
        $this->assertSame(1, substr_count($html, route('training.send-manual-wa', [$this->training, $sent])));

        $this->actingAs($this->admin)->get(route('training.show', $this->training))
            ->assertOk()
            ->assertSee('1 peserta gagal menerima undangan WhatsApp otomatis.')
            ->assertSee('Gagal kirim')
            ->assertSee('Gateway 502 (Server WAGHub sedang sibuk/offline)')
            ->assertSee('Kirim manual')
            ->assertSee(route('training.send-manual-wa', [$this->training, $failed]), false)
            ->assertSee('Terkirim '.$sent->wa_sent_at->isoFormat('D MMM, HH:mm'));

        $this->actingAs($this->admin)->get(route('training.show', [$this->training, 'wa' => 'gagal']))
            ->assertOk()
            // Daftar Zoom di halaman yang sama tetap memuat semua peserta, jadi yang dicek isi tabelnya.
            ->assertViewHas('participants', fn ($participants) => $participants->pluck('id')->all() === [$failed->id])
            ->assertSee('Tampilkan semua peserta');
    }

    public function test_participant_without_a_valid_number_gets_no_manual_link(): void
    {
        $participant = $this->participant(['no_hp' => null]);
        WhatsAppService::sendToParticipant($participant);
        $this->assertSame('Gagal: Nomor WA kosong', $participant->fresh()->wa_status);

        $this->assertNull(WhatsAppService::manualInvitationLinks($this->training, $participant));
        $this->actingAs($this->admin)->get(route('training.show', $this->training))
            ->assertOk()
            ->assertSee('Kirim Manual (No. HP tidak valid)')
            ->assertDontSee(route('training.send-manual-wa', [$this->training, $participant]), false);

        $this->actingAs($this->admin)
            ->post(route('training.send-manual-wa', [$this->training, $participant]))
            ->assertRedirect(route('training.show', $this->training).'#peserta')
            ->assertSessionHas('error');
        $this->assertSame(1, $participant->waLogs()->count(), 'Kirim manual tanpa nomor tidak dicatat.');
    }

    public function test_whatsapp_state_reflects_the_latest_attempt(): void
    {
        $this->assertSame('Belum dikirim', $this->participant([])->waState()['label']);
        $this->assertSame('warning', $this->participant([], ['wa_status' => 'Dalam antrean'])->waState()['kind']);

        $simulated = $this->participant([], ['wa_status' => 'Terkirim (Simulasi / Log)', 'wa_sent_at' => now()])->waState();
        $this->assertSame('success', $simulated['kind']);
        $this->assertStringContainsString('Simulasi', $simulated['detail']);

        // Kiriman ulang gagal setelah pernah terkirim: tampil gagal, dengan waktu kiriman terakhir yang berhasil.
        $resent = $this->participant([], ['wa_status' => 'Error: Koneksi timeout', 'wa_sent_at' => now()])->waState();
        $this->assertSame('danger', $resent['kind']);
        $this->assertStringStartsWith('Koneksi timeout (terakhir terkirim', $resent['detail']);
    }

    public function test_a_gateway_failure_points_the_trainer_to_the_manual_button(): void
    {
        // Gateway lain dari fake bawaan TestCase, supaya fake 200 untuk whatsapp.test tidak lebih dulu cocok.
        config(['services.whatsapp.url' => 'https://wa-down.test']);
        Http::fake(['wa-down.test/*' => Http::response(['message' => 'down'], 502)]);
        $participant = $this->participant(['no_hp' => '081234567890']);

        $this->actingAs($this->admin)
            ->from(route('training.show', $this->training))
            ->post(route('training.send-single-wa', [$this->training, $participant]))
            ->assertSessionHas('error', fn (?string $message) => $message !== null && str_contains($message, 'Kirim manual'));

        $this->assertTrue($participant->fresh()->waFailed());
    }

    public function test_every_automatic_attempt_and_manual_open_is_kept_in_the_history(): void
    {
        $trainer = User::factory()->staff()->trainer()->create(['nama_lengkap' => 'TRAINER SATU']);
        $participant = $this->participant(['nama_lengkap' => 'BUDI', 'no_hp' => '081234567890']);

        // Dua kiriman otomatis berhasil (broadcast lalu kirim ulang oleh trainer), lalu gateway mati.
        $this->travelTo(now()->setTime(8, 0));
        WhatsAppService::sendToParticipant($participant);
        $this->travelTo(now()->setTime(9, 0));
        $this->actingAs($trainer)->post(route('training.send-single-wa', [$this->training, $participant]))->assertSessionHas('success');

        config(['services.whatsapp.url' => 'https://wa-down.test']);
        Http::fake(['wa-down.test/*' => Http::response(['message' => 'down'], 502)]);
        $this->travelTo(now()->setTime(10, 0));
        $this->actingAs($trainer)->post(route('training.send-single-wa', [$this->training, $participant]))->assertSessionHas('error');

        // Cadangan manual: dicatat siapa yang membuka, lalu diarahkan ke WhatsApp dengan teks undangan yang sama.
        $this->travelTo(now()->setTime(10, 5));
        $links = WhatsAppService::manualInvitationLinks($this->training->fresh(), $participant);
        $this->actingAs($trainer)
            ->post(route('training.send-manual-wa', [$this->training, $participant]))
            ->assertRedirect($links['web']);
        $this->actingAs($trainer)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Mobile Safari/537.36')
            ->post(route('training.send-manual-wa', [$this->training, $participant]))
            ->assertRedirect($links['app']);

        $participant = TrainingParticipant::withWaSummary()->with('waLogs.sender')->findOrFail($participant->id);
        $summary = $participant->waSummary();
        $this->assertSame(2, $summary['autoSuccess']);
        $this->assertSame(1, $summary['autoFailed']);
        $this->assertSame(2, $summary['manual']);
        $this->assertSame('09:00', $summary['lastAutoSuccess']->format('H:i'));
        $this->assertSame('10:00', $summary['lastAutoFailed']->format('H:i'));
        $this->assertTrue($participant->waFailed());

        $manual = $participant->waLogs->first();
        $this->assertTrue($manual->isManual());
        $this->assertSame($trainer->id, $manual->sent_by);
        $this->assertSame('081234567890', $manual->phone);
        $failed = $participant->waLogs->firstWhere('success', false);
        $this->assertNotEmpty($failed->detail);
        $this->assertNull($participant->waLogs->last()->sent_by, 'Broadcast/pengingat tidak punya pengirim.');

        $this->actingAs($trainer)->get(route('training.show', $this->training))
            ->assertOk()
            ->assertSee('Otomatis: 2x berhasil, 1x gagal')
            ->assertSee('Manual: 2x')
            ->assertSee('Riwayat Undangan WhatsApp: BUDI')
            ->assertSee('oleh TRAINER SATU')
            ->assertSee('Dibuka manual');

        // Mengeluarkan peserta ikut menghapus riwayatnya.
        $participant->remove();
        $this->assertSame(0, TrainingWaLog::count());
    }

    public function test_failed_only_filter_survives_search_and_explains_an_empty_list(): void
    {
        $failed = $this->participant(['nama_lengkap' => 'BUDI GAGAL', 'no_hp' => '081234567890'], ['wa_status' => 'Gagal: down']);

        $this->actingAs($this->admin)->get(route('training.show', [$this->training, 'wa' => 'gagal']))
            ->assertOk()
            ->assertSee('<input type="hidden" name="wa" value="gagal">', false);

        // Kiriman ulang berhasil: daftar "gagal saja" kosong, dengan penjelasan dan jalan kembali ke semua peserta.
        $failed->update(['wa_status' => 'Terkirim', 'wa_sent_at' => now()]);
        $this->actingAs($this->admin)->get(route('training.show', [$this->training, 'wa' => 'gagal']))
            ->assertOk()
            ->assertSee('Tidak ada undangan WhatsApp yang gagal')
            ->assertSee('Tampilkan semua peserta')
            ->assertDontSee('Daftarkan peserta agar mereka bisa presensi');
    }

    public function test_history_counts_stay_exact_when_only_the_latest_entries_are_loaded(): void
    {
        $participant = $this->participant(['nama_lengkap' => 'BUDI', 'no_hp' => null]);
        foreach (range(1, TrainingParticipant::WA_LOGS_SHOWN + 5) as $attempt) {
            WhatsAppService::sendToParticipant($participant);
        }

        $shown = TrainingParticipant::WA_LOGS_SHOWN;
        $this->actingAs($this->admin)->get(route('training.show', $this->training))
            ->assertOk()
            ->assertSee('Otomatis: 0x berhasil, '.($shown + 5).'x gagal')
            ->assertSee("Menampilkan {$shown} riwayat terbaru.")
            ->assertViewHas('participants', fn ($participants) => $participants->first()->waLogs->count() === $shown);
    }

    public function test_staff_without_training_access_cannot_open_manual_whatsapp(): void
    {
        $participant = $this->participant(['no_hp' => '081234567890']);

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('training.send-manual-wa', [$this->training, $participant]));

        $this->assertSame(0, $participant->waLogs()->count());
    }
}
