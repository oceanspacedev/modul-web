<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BackgroundTaskFinished;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function notify(User $user, array $data = []): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => BackgroundTaskFinished::class,
            'data' => [
                'kind' => 'user-import',
                'status' => BackgroundTaskFinished::STATUS_PARTIAL,
                'title' => 'Import user selesai',
                'message' => 'Import selesai. Berhasil: 3 user. Gagal: 1 baris.',
                'errors' => ['Baris 4: Jabatan tidak ditemukan'],
                ...$data,
            ],
        ]);
    }

    public function test_inbox_shows_only_own_notifications_and_marks_them_read(): void
    {
        $user = User::factory()->staff()->create();
        $mine = $this->notify($user);
        $this->notify(User::factory()->staff()->create(), ['title' => 'Milik orang lain']);

        $this->actingAs($user)->getJson(route('notifications.unread-count'))->assertExactJson(['count' => 1]);

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Import user selesai')
            ->assertSee('Baris 4: Jabatan tidak ditemukan')
            ->assertSee('Baru')
            ->assertDontSee('Milik orang lain');

        $this->assertNotNull($mine->fresh()->read_at);
        $this->actingAs($user)->getJson(route('notifications.unread-count'))->assertExactJson(['count' => 0]);
    }

    public function test_bell_and_sidebar_show_the_unread_count(): void
    {
        $user = User::factory()->staff()->create();
        $this->notify($user);
        $this->notify($user, ['title' => 'Export user selesai']);

        $this->actingAs($user)->get('/panduan')
            ->assertOk()
            ->assertSee('data-notification-count', false)
            ->assertSee('Export user selesai');

        $this->actingAs($user)->post(route('notifications.read-all'))->assertSessionHas('success');
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_users_cannot_delete_other_users_notifications(): void
    {
        $user = User::factory()->staff()->create();
        $theirs = $this->notify(User::factory()->staff()->create());
        $mine = $this->notify($user);

        $this->actingAs($user)->delete(route('notifications.destroy', $theirs->id))->assertNotFound();
        $this->actingAs($user)->delete(route('notifications.destroy', $mine->id))->assertSessionHas('success');

        $this->assertNotNull($theirs->fresh());
        $this->assertNull($mine->fresh());
    }

    public function test_expired_export_redirects_with_a_message(): void
    {
        Storage::fake('local');
        $user = User::factory()->staff()->create();
        $notification = $this->notify($user, ['download_path' => 'exports/gone/file.xlsx', 'download_name' => 'file.xlsx']);

        $this->actingAs($user)->get(route('notifications.download', $notification->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('error');
    }

    public function test_prune_removes_expired_exports_leftover_uploads_and_old_read_notifications(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $disk->put('exports/old/file.xlsx', 'x');
        $disk->put('exports/new/file.xlsx', 'x');
        $disk->put('imports/stale.xlsx', 'x');
        touch($disk->path('exports/old/file.xlsx'), now()->subDays(8)->getTimestamp());
        touch($disk->path('imports/stale.xlsx'), now()->subDays(2)->getTimestamp());

        $user = User::factory()->staff()->create();
        $old = $this->notify($user);
        $old->forceFill(['read_at' => now()->subDays(91)])->save();
        $unread = $this->notify($user);

        $this->artisan('notifications:prune')->assertSuccessful();

        $disk->assertMissing('exports/old/file.xlsx');
        $disk->assertExists('exports/new/file.xlsx');
        $disk->assertMissing('imports/stale.xlsx');
        $this->assertNull($old->fresh());
        $this->assertNotNull($unread->fresh());
    }
}
