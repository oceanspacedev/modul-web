<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Divisi;
use App\Models\DokumenType;
use App\Models\Position;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Daftar master data (Area, Divisi, Posisi, Tipe Dokumen) dicari dan dibagi per halaman oleh server, sama seperti
 * daftar User.
 */
class MasterDataListTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->sole();
    }

    /**
     * @return array<string, array{string, class-string, string}>
     */
    public static function lists(): array
    {
        return [
            'area' => ['area.index', Area::class, 'area'],
            'divisi' => ['divisi.index', Divisi::class, 'divisi'],
            'posisi' => ['position.index', Position::class, 'posisi'],
            'tipe dokumen' => ['dokumentype.index', DokumenType::class, 'tipe dokumen'],
        ];
    }

    #[DataProvider('lists')]
    public function test_list_is_paginated_and_searchable(string $route, string $model, string $noun): void
    {
        $existing = $model::count();
        foreach (range(1, 30) as $number) {
            $model::create(['name' => sprintf('ZZ DATA %03d', $number), ...($model === Divisi::class ? ['area_id' => 1] : [])]);
        }
        $total = $existing + 30;

        // Halaman pertama 25 baris, sisanya di halaman berikutnya; nomor urut berlanjut.
        $first = $this->actingAs($this->admin)->get(route($route))->assertOk();
        $first->assertSee("Total {$total} {$noun}")->assertSee('page=2', false)->assertSee('type="search"', false);
        $this->assertSame(25, substr_count($first->getContent(), 'btn btn-default btn-xs text-danger">Hapus'));

        $second = $this->actingAs($this->admin)->get(route($route, ['page' => 2]))->assertOk();
        $this->assertSame($total - 25, substr_count($second->getContent(), 'btn btn-default btn-xs text-danger">Hapus'));
        $second->assertSee('<td class="text-muted">26</td>', false)->assertSee('ZZ DATA 030');

        // Pencarian dijalankan server di seluruh data, bukan hanya halaman yang tampil, dan ikut di tautan halaman.
        $found = $this->actingAs($this->admin)->get(route($route, ['search' => 'data 03']))->assertOk();
        $found->assertSee("Total 1 {$noun}")->assertSee('ZZ DATA 030')->assertDontSee('ZZ DATA 001');
        $this->actingAs($this->admin)->get(route($route, ['search' => 'ZZ DATA']))->assertOk()
            ->assertSee("Total 30 {$noun}")->assertSee('search=ZZ%20DATA&amp;page=2', false);

        $this->actingAs($this->admin)->get(route($route, ['search' => 'tidak-ada-yang-cocok']))->assertOk()
            ->assertSee('Tidak ada hasil')->assertSee("Total 0 {$noun}")->assertDontSee("Belum ada {$noun}");
    }

    public function test_divisi_is_also_found_by_its_area_or_head(): void
    {
        $area = Area::create(['name' => 'PT SEJAHTERA']);
        $head = User::factory()->staff()->create(['nama_lengkap' => 'RINA KEPALA']);
        Divisi::create(['name' => 'GUDANG', 'area_id' => $area->id]);
        Divisi::create(['name' => 'KEUANGAN', 'area_id' => 1, 'manager_id' => $head->id]);

        $this->actingAs($this->admin)->get(route('divisi.index', ['search' => 'sejahtera']))->assertOk()->assertSee('GUDANG')->assertDontSee('KEUANGAN');
        $this->actingAs($this->admin)->get(route('divisi.index', ['search' => 'rina kepala']))->assertOk()->assertSee('KEUANGAN')->assertDontSee('GUDANG');
    }

    public function test_search_fields_search_while_typing(): void
    {
        // Skrip bersama di layout: kotak pencarian mengirim formnya sendiri saat pengguna berhenti mengetik.
        foreach (['user.index', 'position.index', 'roles.index'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk()
                ->assertSee('search-field-focus', false)->assertSee('class="search-field', false);
        }
    }
}
