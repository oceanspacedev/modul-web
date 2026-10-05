<?php

namespace App\Http\Controllers;

use App\Exports\UserExport;
use App\Exports\UserTemplate;
use App\Imports\UserImport;
use App\Models\Divisi;
use App\Models\JobLevel;
use App\Models\SubDivisi;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('setting.user.index', [
            'title' => 'User',
            'active' => 'setting',
            'users' => User::with(['joblevel', 'divisi', 'subdivisi', 'roles', 'lastSeen'=> function($q){
                $q -> orderBy('last_used_at', 'DESC');
            }])->filter()->withTrashed()->orderBy('full_name')->get(),
        ]);
    }

    public function import(Request $request)
    {
        try {
            $file = $request->file('file');
            $namaFile = $file->getClientOriginalName();
            $file->move(public_path('import'), $namaFile);

            Excel::import(new UserImport, public_path('/import/' . $namaFile));
            unlink(
                public_path('import/' . $namaFile)
            );
            return redirect('user')->with(['success' => 'berhasil import user']);
        } catch (Exception $e) {
            return redirect('user')->with(['error' => $e->getMessage()]);
        }
    }

    public function template()
    {
        return Excel::download(new UserTemplate, 'user_template.xlsx');
    }

    public function export()
    {
        return Excel::download(new UserExport, 'user_export.xlsx');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('setting.user.create', [
            'title' => 'User',
            'active' => 'setting',
            'joblevels' => JobLevel::all(),
            'divisis' => Divisi::all(),
            'roles' => Role::all(),
        ]);
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
            $request->validate([
                'username' => ['required', 'string', 'max:255', 'unique:users,username'],
                'full_name' => ['required', 'string', 'max:255'],
                'id_karyawan' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'string', 'max:100'],
                'no_wa' => ['nullable', 'string', 'max:25'],
                'role' => ['required', 'string', 'exists:roles,name'],
            ]);
            $data = $request->all();
            unset($data['_token']);
            $roleName = $request->input('role', 'Staff');
            unset($data['role']);
            $data['password'] = bcrypt($data['password']);
            $data['full_name'] = strtoupper($data['full_name']);
            $newUser = User::create($data);
            $newUser->assignRole($roleName);

            return redirect('user')->with(['success' => 'Berhasil menambahkan user']);
        } catch (Exception $e) {
            return redirect('user')->with(['error' => $e->getMessage()]);
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
        $user = User::with(['joblevel', 'divisi', 'subdivisi.divisi', 'roles'])->find($id);

        if (!$user) {
            return redirect('user')->with(['error' => 'Tidak bisa mengedit user yang nonaktif, aktifkan terlebih dahulu']);
        }

        return view('setting.user.edit', [
            'title' => 'User',
            'active' => 'setting',
            'user' => $user,
            'joblevels' => JobLevel::all(),
            'divisis' => Divisi::all(),
            'subdivisis' => SubDivisi::where('divisi_id', $user->divisi_id)->get(),
            'roles' => Role::all(),
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
            $token = PersonalAccessToken::where('tokenable_id', $id)->get()->first;
            if ($token) {
                $token->delete();
            }
            $user = User::find($id);
            $request->validate([
                'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],
                'full_name' => ['required', 'string', 'max:255'],
                'id_karyawan' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'string', 'max:100'],
                'no_wa' => ['nullable', 'string', 'max:25'],
                'role' => ['nullable', 'string', 'exists:roles,name'],
            ]);
            $data = $request->all();
            unset($data['_token']);

            if ($request->filled('role')) {
                $user->syncRoles([$request->role]);
            }
            unset($data['role']);

            $data['full_name'] = strtoupper($data['full_name']);
            $subdivisi = SubDivisi::where('divisi_id', $request->divisi_id)->get();
            if (count($subdivisi) === 0) {
                $data['sub_divisi_id'] = null;
            }
            if ($request->filled('password')) {
                $data['password'] = bcrypt($request->password);
            } else {
                unset($data['password']);
            }
            $user->update($data);
            return redirect('user')->with(['success' => 'Berhasil merubah user']);
        } catch (Exception $e) {
            return redirect('user')->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        try {
            $user->delete();
            return redirect('user')->with(['success' => 'Berhasil menonaktifkan user']);
        } catch (Exception $e) {
            return redirect('user')->with(['error' => $e->getMessage()]);
        }
    }

    public function active($id)
    {
        try {
            $user = User::withTrashed()->find($id);
            $user['deleted_at'] = null;
            $user->save();
            return redirect('user')->with(['success' => 'Berhasil mengaktifkan user']);
        } catch (Exception $e) {
            return redirect('user')->with(['error' => $e->getMessage()]);
        }
    }

    public function download(Request $request)
    {
        return response()->download(public_path('/storage/apk/modul.apk'), 'modul.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="android.apk"',
        ]);
    }
}
