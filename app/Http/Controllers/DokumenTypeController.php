<?php

namespace App\Http\Controllers;

use App\Models\DokumenType;
use Exception;
use Illuminate\Http\Request;

class DokumenTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('setting.dokumentype.index', [
            'title' => 'Job Level',
            'active' => 'setting',
            'dokumentypes' => DokumenType::all(),
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
            DokumenType::insert([
                'name' => strtoupper($request->name),
            ]);
            return redirect('dokumentype')->with(['success' => 'berhasil menambahkan dokumentype']);
        } catch (Exception $e) {
            return redirect('dokumentype')->with(['error' => $e->getMessage()]);
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
        return view('setting.dokumentype.edit', [
            'title' => 'Job Level',
            'active' => 'setting',
            'dokumentype' => DokumenType::find($id),
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
            $dokumentype = DokumenType::find($id);
            $dokumentype->update([
                'name' => strtoupper($request->name)
            ]);
            return redirect('dokumentype')->with(['success' => 'berhasil merubah dokumentype']);
        } catch (Exception $e) {
            return redirect('dokumentype')->with(['error' => $e->getMessage()]);
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
            $dokumentype = DokumenType::find($request->id);
            $dokumentype->delete();
            return redirect('dokumentype')->with(['success' => 'berhasil menghapus dokumentype']);
        } catch (Exception $e) {
            return redirect('dokumentype')->with(['error' => $e->getMessage()]);
        }
    }
}
