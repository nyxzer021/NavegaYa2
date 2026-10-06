<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    private const PLATFORM_PERMISSION_CODES = ['companies', 'routes', 'reports', 'ads', 'audit'];

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::with(['roles', 'organizations', 'permissions'])->orderBy('name')->paginate(25),
            'permissions' => Permission::query()
                ->whereIn('code', self::PLATFORM_PERMISSION_CODES)
                ->orderByRaw("CASE code WHEN 'companies' THEN 1 WHEN 'routes' THEN 2 WHEN 'reports' THEN 3 WHEN 'ads' THEN 4 WHEN 'audit' THEN 5 ELSE 6 END")
                ->get()
                ->groupBy('group_name'),
            'organizations' => Organization::where('type', 'transport_company')->where('status', 'active')->orderBy('legal_name')->get(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.users.index');
    }

    public function show(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.index', ['user' => $user->id]);
    }

    public function edit(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.index', ['user' => $user->id]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'], 'organization_id' => ['nullable', 'exists:organizations,id'],
            'permissions' => ['required', 'array', 'min:1'], 'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail()->id, ['organization_id' => $data['organization_id'] ?? null]);
        if (! empty($data['organization_id'])) {
            $user->organizations()->attach($data['organization_id'], ['status' => 'active']);
        }
        $user->permissions()->sync($data['permissions']);

        return back()->with('success', 'Administrador creado. Sus módulos y empresa quedaron asignados.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->roles()->where('code', 'super_admin')->exists(), 403, 'El administrador principal conserva acceso total.');
        $data = $request->validate(['organization_id' => ['nullable', 'exists:organizations,id'], 'permissions' => ['nullable', 'array'], 'permissions.*' => ['integer', 'exists:permissions,id']]);
        $user->permissions()->sync($data['permissions'] ?? []);
        $user->organizations()->sync(! empty($data['organization_id']) ? [$data['organization_id'] => ['status' => 'active']] : []);
        $user->roles()->sync([Role::where('code', 'admin')->value('id') => ['organization_id' => $data['organization_id'] ?? null]]);

        return back()->with('success', 'Empresa y permisos actualizados.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->roles()->where('code', 'super_admin')->exists(), 403, 'El administrador principal no puede eliminarse.');
        $user->permissions()->detach();
        $user->roles()->detach();
        $user->organizations()->detach();
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Usuario de plataforma eliminado.');
    }
}
