<?php

namespace App\Http\Controllers;

use App\Models\JobLevel;
use Exception;
use Illuminate\Http\Request;

class JobLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('setting.joblevel.index', [
            'title' => 'Job Level',
            'active' => 'setting',
            'joblevels' => JobLevel::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            JobLevel::insert([
                'name' => strtoupper($request->name),
            ]);
            return redirect('joblevel')->with(['success' => 'berhasil menambahkan job level']);
        } catch (Exception $e) {
            return redirect('joblevel')->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('setting.joblevel.edit', [
            'title' => 'Job Level',
            'active' => 'setting',
            'joblevel' => JobLevel::find($id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $joblevel = JobLevel::find($id);
            $joblevel->update([
                'name' => strtoupper($request->name)
            ]);
            return redirect('joblevel')->with(['success' => 'berhasil merubah job level']);
        } catch (Exception $e) {
            return redirect('joblevel')->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        try {
            $joblevel = JobLevel::find($request->id);
            $joblevel->delete();
            return redirect('joblevel')->with(['success' => 'berhasil menghapus job level']);
        } catch (Exception $e) {
            return redirect('joblevel')->with(['error' => $e->getMessage()]);
        }
    }
}
