<?php

namespace Tests\Feature;

use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestRecipient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WhatsApp saat Permintaan Dokumen dikirim: pemohon menerima kode lacak, pengelola menerima kabar pengajuan baru.
 */
class DocumentRequestNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        config(['services.whatsapp.token' => 'token-uji', 'services.whatsapp.url' => 'https://whatsapp.test']);
        Http::fake(['whatsapp.test/*' => Http::response(['status' => true])]);
    }

    private function member(string $access): User
    {
        $user = User::factory()->staff()->create();
        $user->setOdReportAccess($access);

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function payload(string $code = 'IK', array $overrides = []): array
    {
        return [
            'kategori' => DocumentCategory::where('code', $code)->value('id'),
            'nama' => 'Budi Pemohon',
            'divisi' => 'Gudang',
            'departemen' => 'Warehouse',
            'jabatan' => 'Leader',
            'whatsapp' => '081234567890',
            'email' => 'budi@example.com',
            'proses' => [
                ['judul' => 'Penerimaan barang', 'tujuan' => 'Supaya penerimaan barang seragam.', 'prioritas' => 'High'],
                ['judul' => 'Retur barang', 'tujuan' => 'Alur retur sering berbeda.', 'prioritas' => 'Urgent'],
            ],
            'obs_tanggal_1' => '2026-11-20',
            'idempotency_key' => 'kunci-notif-1',
            ...$overrides,
        ];
    }

    /** @return array<string, string> nomor tujuan => teks pesan */
    private function sentMessages(): array
    {
        return Http::recorded()->mapWithKeys(fn (array $pair) => [
            $pair[0]['recipient']['value'] => $pair[0]['message']['text'],
        ])->all();
    }

    public function test_requester_gets_tracking_code_and_every_manager_gets_the_new_request(): void
    {
        DocumentRequestRecipient::create(['name' => 'Admin OD', 'whatsapp' => '6281111111111']);
        DocumentRequestRecipient::create(['name' => 'Leader OD', 'whatsapp' => '6282222222222']);

        $this->post(route('document-request.store'), $this->payload());
        $request = DocumentRequest::sole();
        $messages = $this->sentMessages();

        $this->assertCount(3, $messages);
        $this->assertStringContainsString("Kode lacak: *{$request->code}*", $messages['081234567890']);
        $this->assertStringContainsString('1. Penerimaan barang', $messages['081234567890']);
        $this->assertStringContainsString(route('document-request.track', ['kode' => $request->code]), $messages['081234567890']);

        foreach (['081111111111', '082222222222'] as $manager) {
            $this->assertStringContainsString("Pengajuan Dokumen Baru {$request->code}", $messages[$manager]);
            $this->assertStringContainsString('IK · Instruksi Kerja', $messages[$manager]);
            $this->assertStringContainsString('Budi Pemohon (Leader · Warehouse)', $messages[$manager]);
            $this->assertStringContainsString('2. Retur barang (Urgent)', $messages[$manager]);
            $this->assertStringContainsString('Observasi: Jumat, 20 November 2026', $messages[$manager]);
            $this->assertStringContainsString(route('od-report.requests.show', $request), $messages[$manager]);
        }

        // Kiriman ganda dengan kunci yang sama tidak mengirim pesan lagi.
        $this->post(route('document-request.store'), $this->payload());
        Http::assertSentCount(3);
    }

    public function test_without_managers_the_od_admin_number_is_used_when_set(): void
    {
        $this->post(route('document-request.store'), $this->payload('JD'));
        // Tanpa nomor pengelola dan tanpa nomor admin OD: hanya pemohon yang dikabari.
        $this->assertSame(['081234567890'], array_keys($this->sentMessages()));

        config(['od-report.admin_whatsapp' => '6289999999999']);
        $this->post(route('document-request.store'), $this->payload('JD', ['idempotency_key' => 'kunci-notif-2']));
        $this->assertStringContainsString('Pengajuan Dokumen Baru', $this->sentMessages()['089999999999']);
    }

    public function test_leader_manages_manager_numbers(): void
    {
        $leader = $this->member(User::OD_LEADER);
        $staff = $this->member(User::OD_STAFF);

        $this->actingAs($staff)->post(route('document-category.recipients.store'), ['recipient_name' => 'X', 'whatsapp' => '081234567890'])->assertRedirect();
        $this->assertSame(0, DocumentRequestRecipient::count());

        $this->actingAs($leader)->post(route('document-category.recipients.store'), ['recipient_name' => 'Admin OD', 'whatsapp' => '0812-3456-7890'])
            ->assertSessionHas('success');
        $recipient = DocumentRequestRecipient::sole();
        $this->assertSame('6281234567890', $recipient->whatsapp);

        $this->actingAs($leader)->get(route('document-category.index'))->assertOk()->assertSee('Admin OD')->assertSee('081234567890');

        $this->actingAs($leader)->post(route('document-category.recipients.store'), ['recipient_name' => 'Kembar', 'whatsapp' => '+62 812 3456 7890'])
            ->assertSessionHasErrors('whatsapp', null, 'recipient');
        $this->actingAs($leader)->post(route('document-category.recipients.store'), ['recipient_name' => 'Salah', 'whatsapp' => 'bukan nomor'])
            ->assertSessionHasErrors('whatsapp', null, 'recipient');

        $this->actingAs($leader)->delete(route('document-category.recipients.destroy', $recipient))->assertSessionHas('success');
        $this->assertModelMissing($recipient);
    }
}
