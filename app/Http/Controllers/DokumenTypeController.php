<?php

namespace App\Http\Controllers;

use App\Http\Requests\DokumenTypeRequest;
use App\Models\DokumenType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DokumenTypeController extends Controller
{
    public function index(): View
    {
        return view('setting.dokumentype.index', [
            'title' => 'Tipe Dokumen',
            'active' => 'setting',
            'dokumentypes' => DokumenType::orderBy('name')->get(),
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
