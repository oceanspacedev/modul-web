<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Document;
use App\Models\Quiz;
use App\Models\QuizHistory;
use DB;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $totalUser = User::count();
        $totalDocument = Document::count();
        $totalQuiz = Quiz::count();
        $totalHistory = QuizHistory::count();


        $users = User::where('deleted_at', null)
        ->selectRaw('created_at, MONTH(created_at) as bulan')
        ->get()
        ->groupBy('created_at', 'bulan');

        return view('dashboard.index',[
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'data' => [
                'totalUser' => $totalUser,
                'totalDocument' => $totalDocument,
                'totalQuiz' => $totalQuiz,
                'totalHistory' => $totalHistory,
                'users' => $users,
            ]
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
        //
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
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
