<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->orderBy('id')->get();

        return view('user.index', compact('users'));
    }

    public function detail($id)
    {
        $users = User::find($id);
        return view('auth.detail', ['users' => $users]);
    }

    public function delete($id)
    {
        $users = User::find($id);
        $users->delete();
        Alert::success('Delete Successfully!', 'User ' . $users->name . ' successfully deleted!');
        return redirect()->intended('user/index');
    }

    public function assign($id)
    {
        $user = User::with('roles')->findOrFail($id);

        return view('auth.assign', [
            'user'  => $user,
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function assignrole(Request $request)
    {
        $request->validate([
            'id'      => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $user = User::findOrFail($request->id);
        $role = Role::findOrFail($request->role_id);

        $user->syncRoles([$role->name]);

        Alert::success('Assign Successfully!', 'User ' . $user->name . ' sekarang punya role ' . $role->name . '.');
        return redirect()->route('user.index');
    }

    public function update(Request $request)
    {
        $user = User::findOrFail($request->id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|',
            'dept' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user->fill([
            'name' => $request->name,
            'dept' => $request->dept,
            'email' => $request->email,
        ]);

        $user->save();

        Alert::success('Update Successfully!', 'User ' . $request->name . ' successfully updated!');
        return redirect()->intended('user/index');
    }
}
