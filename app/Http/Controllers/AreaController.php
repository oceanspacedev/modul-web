<?php

namespace App\Http\Controllers;

use App\Http\Requests\AreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function index(): View
    {
        return view('setting.area.index', [
            'title' => 'Area',
            'active' => 'setting',
            'areas' => Area::withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function store(AreaRequest $request): RedirectResponse
    {
        Area::create($request->validated());

        return to_route('area.index')->with('success', 'Berhasil menambahkan area');
    }

    public function edit(Area $area): View
    {
        return view('setting.area.edit', [
            'title' => 'Area',
            'active' => 'setting',
            'area' => $area,
        ]);
    }

    public function update(AreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        return to_route('area.index')->with('success', 'Berhasil mengubah area');
    }

    public function destroy(Area $area): RedirectResponse
    {
        // Area tidak memakai soft delete; karyawan tidak boleh ditinggalkan tanpa badan usaha.
        if ($area->users()->withTrashed()->exists()) {
            return to_route('area.index')->with('error', 'Area masih dipakai karyawan, pindahkan karyawannya terlebih dahulu');
        }

        $area->delete();

        return to_route('area.index')->with('success', 'Berhasil menghapus area');
    }
}
