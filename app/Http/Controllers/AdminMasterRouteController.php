<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreMasterRouteRequest;
use App\Models\MasterRoute;
use App\Models\Port;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminMasterRouteController extends Controller
{
    public function index(): View
    {
        $this->authorizeSuperAdmin();

        return view('admin.master-routes.index', [
            'masterRoutes' => MasterRoute::with(['originPort', 'destinationPort'])
                ->withCount(['transportRoutes', 'airRoutes'])
                ->orderBy('modality')->orderBy('origin_city')->orderBy('destination_city')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorizeSuperAdmin();

        return view('admin.master-routes.form', $this->formData());
    }

    public function store(StoreMasterRouteRequest $request): RedirectResponse
    {
        MasterRoute::create($request->validated());

        return redirect()->route('admin.master-routes.index')->with('success', 'Tramo maestro creado correctamente.');
    }

    public function show(MasterRoute $masterRoute): RedirectResponse
    {
        return redirect()->route('admin.master-routes.edit', $masterRoute);
    }

    public function edit(MasterRoute $masterRoute): View
    {
        $this->authorizeSuperAdmin();

        return view('admin.master-routes.form', $this->formData($masterRoute));
    }

    public function update(StoreMasterRouteRequest $request, MasterRoute $masterRoute): RedirectResponse
    {
        $masterRoute->update($request->validated());

        return redirect()->route('admin.master-routes.index')->with('success', 'Tramo maestro actualizado.');
    }

    public function destroy(MasterRoute $masterRoute): RedirectResponse
    {
        $this->authorizeSuperAdmin();
        if ($masterRoute->transportRoutes()->exists() || $masterRoute->airRoutes()->exists()) {
            return back()->with('error', 'No se puede eliminar un tramo utilizado por operadores. Suspéndelo para conservar la trazabilidad.');
        }
        $masterRoute->delete();

        return back()->with('success', 'Tramo maestro eliminado.');
    }

    /** @return array{masterRoute: ?MasterRoute, ports: Collection<int, Port>} */
    private function formData(?MasterRoute $masterRoute = null): array
    {
        return [
            'masterRoute' => $masterRoute,
            'ports' => Port::query()->where('is_active', true)->orderBy('city')->orderBy('name')->get(),
        ];
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->roles()->where('code', 'super_admin')->exists(), 403);
    }
}
