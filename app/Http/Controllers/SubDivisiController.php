<?php

namespace App\Http\Controllers;

use App\Models\Divisi;
use App\Models\SubDivisi;
use Exception;
use Illuminate\Http\Request;

class SubDivisiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('setting.subdivisi.index',[
            'title' => 'Divisi',
            'active' => 'setting',
            'divisis' => Divisi::all(),
            'subdivisis' => SubDivisi::with('divisi')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

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
            SubDivisi::insert([
                'divisi_id' => $request->divisi_id,
                'name' => strtoupper($request->name),
            ]);
            return redirect('subdivisi')->with(['success' => 'berhasil menambahkan subdivisi']);
        } catch (Exception $e) {
            return redirect('subdivisi')->with(['error' => $e->getMessage()]);
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
        $subdivisi = SubDivisi::where('divisi_id', $id)->orderBy('name')->get();
        return response()->json($subdivisi);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('setting.subdivisi.edit', [
            'title' => 'Divisi',
            'active' => 'setting',
            'divisis' => Divisi::all(),
            'subdivisi' => SubDivisi::find($id),
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
            $subdivisi = SubDivisi::find($id);
            $subdivisi->update([
                'divisi_id' => $request->divisi_id,
                'name' => strtoupper($request->name)
            ]);
            return redirect('subdivisi')->with(['success' => 'berhasil merubah subdivisi']);
        } catch (Exception $e) {
            return redirect('subdivisi')->with(['error' => $e->getMessage()]);
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
            $subdivisi = SubDivisi::find($request->id);
            $subdivisi->delete();
            return redirect('subdivisi')->with(['success' => 'berhasil menghapus subdivisi']);
        } catch (Exception $e) {
            return redirect('subdivisi')->with(['error' => $e->getMessage()]);
        }
    }
}
