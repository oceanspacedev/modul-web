<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubDivisiRequest;
use App\Models\Divisi;
use App\Models\SubDivisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubDivisiController extends Controller
{
    public function index(): View
    {
        return view('setting.subdivisi.index', [
            'title' => 'Sub Divisi',
            'active' => 'setting',
            'divisis' => Divisi::orderBy('name')->get(),
            'subdivisis' => SubDivisi::with('divisi')->orderBy('name')->get(),
        ]);
    }

    /**
     * Sub divisions of one division, for the dependent dropdowns on the user and document forms.
     */
    public function byDivisi(Divisi $divisi): JsonResponse
    {
        return response()->json($divisi->subdivisi()->orderBy('name')->get());
    }

    public function store(SubDivisiRequest $request): RedirectResponse
    {
        SubDivisi::create($request->validated());

        return to_route('subdivisi.index')->with('success', 'Berhasil menambahkan sub divisi');
    }

    public function edit(SubDivisi $subdivisi): View
    {
        return view('setting.subdivisi.edit', [
            'title' => 'Sub Divisi',
            'active' => 'setting',
            'divisis' => Divisi::orderBy('name')->get(),
            'subdivisi' => $subdivisi,
        ]);
    }

    public function update(SubDivisiRequest $request, SubDivisi $subdivisi): RedirectResponse
    {
        $subdivisi->update($request->validated());

        return to_route('subdivisi.index')->with('success', 'Berhasil mengubah sub divisi');
    }

    public function destroy(SubDivisi $subdivisi): RedirectResponse
    {
        $subdivisi->delete();

        return to_route('subdivisi.index')->with('success', 'Berhasil menghapus sub divisi');
    }
}
