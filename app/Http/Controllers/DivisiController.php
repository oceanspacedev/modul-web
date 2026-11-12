<?php

namespace App\Http\Controllers;

use App\Http\Requests\DivisiRequest;
use App\Models\Area;
use App\Models\Divisi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DivisiController extends Controller
{
    /** Baris per halaman di daftar master data. */
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        return view('setting.divisi.index', [
            'title' => 'Divisi',
            'active' => 'setting',
            'divisis' => Divisi::with(['area', 'manager'])->withCount('users')
                ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                    ->where('name', 'like', "%{$search}%")
                    ->orWhereHas('area', fn ($area) => $area->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('manager', fn ($manager) => $manager->where('nama_lengkap', 'like', "%{$search}%"))))
                ->orderBy('name')
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
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
