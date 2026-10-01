<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Helpers\ResponseFormatter;
use App\Models\Absent;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AbsentExport;
use App\Helpers\ConvertDate;
use Carbon\Carbon;

class AbsentController extends Controller
{
    public function index(Request $request){
        if ($request->date) {
            $data = explode('-', preg_replace('/\s+/', '', $request->date));
            $date1 = Carbon::parse($data[0])->format('Y-m-d');
            $date2 = Carbon::parse($data[1])->format('Y-m-d');
            $date2 = date('Y-m-d', strtotime('+ 1 day', strtotime($date2)));
            $absents = Absent::with('user')
                ->whereBetween('created_at', [$date1, $date2])
                ->orderBy('created_at')
                ->get();
        } else {
            $absents = Absent::orderBy('created_at','DESC')->simplePaginate(10);
        }

        return view('absent.index',[
            'title' => 'Absent',
            'active' => 'absent',
            'absents' => $absents,
        ]);
    }

    public function checkAbsent(Request $request)
    {
    // user cal api param date -> check table absent -> kalo ada -> return error -> balik ke mobile itu false
    // kalo misal tidak ada data di table absent -> create data -> return ok -> balik ke mobile itu true
        try {
            $absen = Absent::where('user_id',auth()->id())->whereDate('created_at',$request->date)->get();
            if (count($absen)) {
                return ResponseFormatter::error(null, "Anda sudah absen hari ini");
            }

            return ResponseFormatter::success(null, 'Anda belum absen hari ini');

        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    public function create(Request $request){
        try {
            $insert_absent = Absent::create([
                'user_id' => auth()->id(),
                'keterangan' => ''
            ]);
            return ResponseFormatter::success(null, 'Selamat anda telah absen');
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    public function exportAbsent(Request $request){
        if ($request->exportAbsent) {
            $data = explode('-', preg_replace('/\s+/', '', $request->exportAbsent));
            $date1 = Carbon::parse($data[0])->format('Y-m-d');
            $date2 = Carbon::parse($data[1])->format('Y-m-d');
            $absents = Absent::with('user')
                ->whereBetween('created_at', [$date1, $date2])
                ->orderBy('created_at')
                ->get();
        }

        return Excel::download(new AbsentExport($date1, $date2), 'absent_' . $date1 . '_to_' . $date2 . '.xlsx',);
    }
}
