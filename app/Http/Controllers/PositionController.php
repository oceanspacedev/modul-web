<?php

namespace App\Http\Controllers;

use App\Http\Requests\PositionRequest;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PositionController extends Controller
{
    /** Baris per halaman di daftar master data. */
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        return view('setting.position.index', [
            'title' => 'Posisi',
            'active' => 'setting',
            'positions' => Position::withCount('users')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function store(PositionRequest $request): RedirectResponse
    {
        Position::create($request->validated());

        return to_route('position.index')->with('success', 'Berhasil menambahkan posisi');
    }

    public function edit(Position $position): View
    {
        return view('setting.position.edit', [
            'title' => 'Posisi',
            'active' => 'setting',
            'position' => $position,
        ]);
    }

    public function update(PositionRequest $request, Position $position): RedirectResponse
    {
        $position->update($request->validated());

        return to_route('position.index')->with('success', 'Berhasil mengubah posisi');
    }

    public function destroy(Position $position): RedirectResponse
    {
        $position->delete();

        return to_route('position.index')->with('success', 'Berhasil menghapus posisi');
    }
}
