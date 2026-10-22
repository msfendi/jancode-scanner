<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RealRashid\SweetAlert\Facades\Alert;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function index()
    {
        return view('auth.register');
    }

    public function create()
    {
        return view('auth.registration', [
            'roles'       => Role::orderBy('id')->get(),
            'defaultRole' => (string) config('rbac.default_role', 'ctpat'),
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Role default supaya user baru tidak terkunci dari semua modul.
        $user->assignRole((string) config('rbac.default_role', 'ctpat'));

        if (Auth::attempt(['email' => $user->email, 'password' => $request->password])) {
            $request->session()->regenerate();

            Alert::success('Create Successfully!', 'User ' . $request->name . ' successfully created!');
            return redirect()->intended('home');
        }
    }

    public function storeAuth(Request $request)
    {
        $this->validate($request, [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'dept' => $request->dept,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Role yang dipilih (atau role default bila tidak diisi).
        $role = $request->filled('role_id') ? Role::find($request->role_id) : null;
        $user->assignRole($role?->name ?? (string) config('rbac.default_role', 'ctpat'));

        Alert::success('Create Successfully!', 'User ' . $request->name . ' successfully created!');
        return redirect()->intended('user/index');
    }
}
