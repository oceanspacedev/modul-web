<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Tests\TestCase;

class OperationsToolsTest extends TestCase
{
    public function test_admin_can_open_horizon_and_log_viewer(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/horizon')->assertOk();
        $this->actingAs($admin)->get('/log-viewer')->assertOk();
    }

    public function test_non_admins_cannot_open_horizon_or_log_viewer(): void
    {
        $users = [
            User::factory()->staff()->trainer()->create(),
            User::factory()->staff()->create(),
            // ADMIN dengan akses data terbatas bukan super admin.
            User::factory()->state(['data_access' => User::DATA_ACCESS_SELECTED])->jabatan(User::ROLE_ADMIN)->create(),
        ];

        foreach ($users as $user) {
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

    public function test_horizon_runs_workers_in_every_deployed_environment(): void
    {
        // Tanpa supervisor untuk APP_ENV server, job antrean (mis. Broadcast WA) tidak pernah diproses.
        foreach (['production', 'staging', 'local'] as $environment) {
            $this->assertNotEmpty(config("horizon.environments.{$environment}"), "Horizon tidak punya worker untuk {$environment}.");
        }
    }

    public function test_upload_urls_follow_the_default_filesystem_disk(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.disks.s3.key' => 'test-key',
            'filesystems.disks.s3.secret' => 'test-secret',
            'filesystems.disks.s3.region' => 'us-east-1',
            'filesystems.disks.s3.bucket' => 'modul',
            'filesystems.disks.s3.endpoint' => 'https://storage.example.test',
            'filesystems.disks.s3.use_path_style_endpoint' => true,
            'filesystems.disks.s3.url' => 'https://storage.example.test/modul',
        ]);

        $document = new Document(['path' => 'sop-v1.pdf']);

        // The bucket need not be publicly readable, so S3 links are signed.
        $this->assertStringStartsWith('https://storage.example.test/modul/dokumen/sop-v1.pdf?', $document->file_url);
        $this->assertStringContainsString('X-Amz-Signature=', $document->file_url);
    }
}
