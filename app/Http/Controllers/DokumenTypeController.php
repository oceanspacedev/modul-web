<?php

namespace App\Http\Controllers;

use App\Http\Requests\DokumenTypeRequest;
use App\Models\DokumenType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DokumenTypeController extends Controller
{
    /** Baris per halaman di daftar master data. */
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        return view('setting.dokumentype.index', [
            'title' => 'Tipe Dokumen',
            'active' => 'setting',
            'dokumentypes' => DokumenType::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function store(DokumenTypeRequest $request): RedirectResponse
    {
        DokumenType::create($request->validated());

        return to_route('dokumentype.index')->with('success', 'Berhasil menambahkan tipe dokumen');
    }

    public function edit(DokumenType $dokumentype): View
    {
        return view('setting.dokumentype.edit', [
            'title' => 'Tipe Dokumen',
            'active' => 'setting',
            'dokumentype' => $dokumentype,
        ]);
    }

    public function update(DokumenTypeRequest $request, DokumenType $dokumentype): RedirectResponse
    {
        $dokumentype->update($request->validated());

        return to_route('dokumentype.index')->with('success', 'Berhasil mengubah tipe dokumen');
    }

    public function destroy(DokumenType $dokumentype): RedirectResponse
    {
        $dokumentype->delete();

        return to_route('dokumentype.index')->with('success', 'Berhasil menghapus tipe dokumen');
    }
}
