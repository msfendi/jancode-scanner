<?php

namespace App\Http\Controllers;

use App\Support\Rbac;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Daftar role + permission matrix.
     */
    public function index()
    {
        $roles = Role::with('permissions')->orderBy('id')->get();

        return view('role.index', [
            'roles'      => $roles,
            'groups'     => Rbac::groups(),
            'allPerms'   => Rbac::allPermissions(),
            'protected'  => (array) config('rbac.protected_roles', []),
        ]);
    }

    public function create()
    {
        return view('role.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_\-]+$/i', 'unique:roles,name'],
            'guard_name' => ['required', 'string', 'max:255'],
        ]);

        Role::create([
            'name'       => $request->name,
            'guard_name' => $request->guard_name,
        ]);

        Alert::success('Create Successfully!', 'Role successfully created!');
        return redirect()
            ->route('role.create');
    }

    /**
     * Simpan permission untuk sebuah role (RBAC).
     */
    public function updatePermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $selected = array_values(array_intersect(
            (array) $request->input('permissions', []),
            Rbac::allPermissions()
        ));

        $role->syncPermissions($selected);

        Alert::success(
            'Hak Akses Disimpan!',
            "Permission untuk role {$role->name} diperbarui (" . count($selected) . ' permission).'
        );

        return redirect()->route('role.index');
    }

    public function delete($id)
    {
        $role = Role::find($id);

        if (! $role) {
            return redirect()->route('role.index')->with('error', 'Role tidak ditemukan.');
        }

        if (in_array($role->name, (array) config('rbac.protected_roles', []), true)) {
            return redirect()->route('role.index')
                ->with('error', "Role {$role->name} dilindungi dan tidak bisa dihapus.");
        }

        $name = $role->name;
        $role->delete();

        Alert::success('Delete Successfully!', 'Role ' . $name . ' successfully deleted!');
        return redirect()->route('role.index');
    }
}
