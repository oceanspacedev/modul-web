<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobLevelRequest;
use App\Models\JobLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JobLevelController extends Controller
{
    public function index(): View
    {
        return view('setting.joblevel.index', [
            'title' => 'Job Level',
            'active' => 'setting',
            'joblevels' => JobLevel::orderBy('id')->get(),
        ]);
    }

    public function store(JobLevelRequest $request): RedirectResponse
    {
        JobLevel::create($request->validated());

        return to_route('joblevel.index')->with('success', 'Berhasil menambahkan job level');
    }

    public function edit(JobLevel $joblevel): View
    {
        return view('setting.joblevel.edit', [
            'title' => 'Job Level',
            'active' => 'setting',
            'joblevel' => $joblevel,
        ]);
    }

    public function update(JobLevelRequest $request, JobLevel $joblevel): RedirectResponse
    {
        $joblevel->update($request->validated());

        return to_route('joblevel.index')->with('success', 'Berhasil mengubah job level');
    }

    public function destroy(JobLevel $joblevel): RedirectResponse
    {
        $joblevel->delete();

        return to_route('joblevel.index')->with('success', 'Berhasil menghapus job level');
    }
}
