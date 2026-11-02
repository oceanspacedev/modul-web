<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Tests\TestCase;

class OperationsToolsTest extends TestCase
{
    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['job_level_id' => 2]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_admin_can_open_horizon_and_log_viewer(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get('/horizon')->assertOk();
        $this->actingAs($admin)->get('/log-viewer')->assertOk();
    }

    public function test_non_admins_cannot_open_horizon_or_log_viewer(): void
    {
        foreach (['Trainer', 'Staff'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get('/horizon')->assertForbidden();
            // Denied gates in the browser are turned into a redirect with an error flash (bootstrap/app.php).
            $this->actingAs($user)->get('/log-viewer')->assertRedirect()->assertSessionHas('error');
        }
    }

    public function test_guests_cannot_open_horizon_or_log_viewer(): void
    {
        $this->get('/horizon')->assertForbidden();
        $this->get('/log-viewer')->assertRedirect()->assertSessionHas('error');
    }

    public function test_upload_urls_follow_the_default_filesystem_disk(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.disks.s3.bucket' => 'modul',
            'filesystems.disks.s3.url' => 'https://storage.example.test/modul',
        ]);

        $document = new Document(['path' => 'sop-v1.pdf']);

        $this->assertSame('https://storage.example.test/modul/dokumen/sop-v1.pdf', $document->file_url);
    }
}
