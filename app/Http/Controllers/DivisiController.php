<?php

namespace App\Http\Controllers;

use App\Http\Requests\DivisiRequest;
use App\Models\Area;
use App\Models\Divisi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DivisiController extends Controller
{
    public function index(): View
    {
        return view('setting.divisi.index', [
            'title' => 'Divisi',
            'active' => 'setting',
            'divisis' => Divisi::with(['area', 'manager'])->withCount('users')->orderBy('name')->get(),
            'areas' => Area::orderBy('name')->get(),
            'managers' => User::orderBy('nama_lengkap')->get(['id', 'nama_lengkap']),
        ]);
    }

    public function store(DivisiRequest $request): RedirectResponse
    {
        Divisi::create($request->validated());

        return to_route('divisi.index')->with('success', 'Berhasil menambahkan divisi');
    }

    public function edit(Divisi $divisi): View
    {
        return view('setting.divisi.edit', [
            'title' => 'Divisi',
            'active' => 'setting',
            'divisi' => $divisi,
            'areas' => Area::orderBy('name')->get(),
            'managers' => User::orderBy('nama_lengkap')->get(['id', 'nama_lengkap']),
        ]);
    }

    public function update(DivisiRequest $request, Divisi $divisi): RedirectResponse
    {
        $divisi->update($request->validated());

        return to_route('divisi.index')->with('success', 'Berhasil mengubah divisi');
    }

    public function destroy(Divisi $divisi): RedirectResponse
    {
        $divisi->delete();

        return to_route('divisi.index')->with('success', 'Berhasil menghapus divisi');
    }
}
