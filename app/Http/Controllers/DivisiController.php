<?php

namespace App\Http\Controllers;

use App\Models\Divisi;
use Exception;
use Illuminate\Http\Request;

class DivisiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('setting.divisi.index',[
            'title' => 'Divisi',
            'active' => 'setting',
            'divisis' => Divisi::all(),
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
            Divisi::insert([
                'name' => strtoupper($request->name),
            ]);
            return redirect('divisi')->with(['success' => 'berhasil menambahkan divisi']);
        } catch (Exception $e) {
            return redirect('divisi')->with(['error' => $e->getMessage()]);
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
        return view('setting.divisi.edit', [
            'title' => 'Job Level',
            'active' => 'setting',
            'divisi' => Divisi::find($id),
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
            $divisi = Divisi::find($id);
            $divisi->update([
                'name' => strtoupper($request->name)
            ]);
            return redirect('divisi')->with(['success' => 'berhasil merubah divisi']);
        } catch (Exception $e) {
            return redirect('divisi')->with(['error' => $e->getMessage()]);
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
            $divisi = Divisi::find($request->id);
            $divisi->delete();
            return redirect('divisi')->with(['success' => 'berhasil menghapus divisi']);
        } catch (Exception $e) {
            return redirect('divisi')->with(['error' => $e->getMessage()]);
        }
    }
}
