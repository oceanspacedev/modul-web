<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Halaman Panduan Penggunaan: gambar yang dirujuk harus ada, dan isinya mengikuti cara kerja aplikasi saat ini.
 */
class GuidePageTest extends TestCase
{
    public function test_every_guide_image_exists(): void
    {
        $html = $this->actingAs(User::factory()->staff()->create())->get('/panduan')->assertOk()->getContent();

        preg_match_all('#src="(/assets/panduan/[^"]+)"#', $html, $matches);

        $this->assertNotEmpty($matches[1]);
        foreach (array_unique($matches[1]) as $src) {
            $this->assertFileExists(public_path(ltrim($src, '/')), "Gambar panduan {$src} tidak ada di folder public.");
        }
    }

    public function test_guide_describes_background_processing_pagination_and_quiz_attempts(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get('/panduan')
            ->assertOk()
            // Import soal dan proses AI berjalan di belakang layar.
            ->assertSee('Import Excel / CSV berjalan di belakang layar')
            ->assertSee('seluruh file dibatalkan')
            ->assertSee('Proses AI')
            // Daftar yang dipaginasi dan pemilih peserta bertahap.
            ->assertSee('25 user per halaman')
            ->assertSee('50 peserta per halaman')
            ->assertSee('Daftar menampilkan 100 user dulu')
            ->assertSee('training-peserta.gif', false)
            // Batas percobaan kuis ditentukan pemateri.
            ->assertSee('Kerjakan Ulang')
            ->assertDontSee('Kuis hanya');
    }

    public function test_guide_documents_impersonation_and_the_return_button(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get('/panduan')
            ->assertOk()
            // Bagian baru: cara masuk sebagai user lain dan cara kembali ke akun sendiri.
            ->assertSee('Masuk sebagai user lain (Impersonate)')
            ->assertSee('Kembali ke akun saya')
            // Copy Keluar tidak boleh lagi bertentangan dengan keadaan saat impersonate.
            ->assertSee('posisi itu digantikan tombol')
            ->assertSee('menu itu tidak menampilkan');
    }
}
