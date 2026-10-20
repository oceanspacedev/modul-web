<?php

namespace App\Http\Controllers;

use App\Http\Requests\PositionRequest;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(): View
    {
        return view('setting.position.index', [
            'title' => 'Posisi',
            'active' => 'setting',
            'positions' => Position::withCount('users')->orderBy('name')->get(),
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
