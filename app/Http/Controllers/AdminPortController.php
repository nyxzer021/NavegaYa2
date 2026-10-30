<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StorePortRequest;
use App\Models\GeographicDepartment;
use App\Models\GeographicDistrict;
use App\Models\GeographicProvince;
use App\Models\Port;
use App\Models\Vessel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPortController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.ports.index', $this->formData([
            'ports' => Port::with(['department', 'province', 'district'])
                ->withCount(['originMasterRoutes', 'destinationMasterRoutes', 'originRoutes', 'destinationRoutes', 'baseVessels'])
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$request->string('search').'%')->orWhere('city', 'like', '%'.$request->string('search').'%')))
                ->when($request->filled('modality'), fn ($query) => $query->where('modality', $request->string('modality')))
                ->orderBy('modality')->orderBy('city')->orderBy('name')->get(),
        ]));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.ports.index', ['nuevo' => 1])->withFragment('nuevo-puerto');
    }

    public function store(StorePortRequest $request): RedirectResponse
    {
        Port::create($this->normalized($request));

        return redirect()->route('admin.ports.index')->with('success', 'Puerto registrado correctamente.');
    }

    public function edit(Port $port): View
    {
        return view('admin.ports.edit', $this->formData(['port' => $port]));
    }

    public function show(Port $port): RedirectResponse
    {
        return redirect()->route('admin.ports.edit', $port);
    }

    public function update(StorePortRequest $request, Port $port): RedirectResponse
    {
        $port->update($this->normalized($request));

        return redirect()->route('admin.ports.index')->with('success', 'Puerto actualizado correctamente.');
    }

    public function provinces(GeographicDepartment $department): JsonResponse
    {
        abort_unless($department->code === '16', 404);

        return response()->json($department->provinces()->orderBy('name')->get(['id', 'name']));
    }

    public function districts(GeographicProvince $province): JsonResponse
    {
        abort_unless($province->department?->code === '16', 404);

        return response()->json($province->districts()->orderBy('name')->get(['id', 'name']));
    }

    public function destroy(Port $port): RedirectResponse
    {
        $inUse = $port->originMasterRoutes()->exists()
            || $port->destinationMasterRoutes()->exists()
            || $port->originRoutes()->exists()
            || $port->destinationRoutes()->exists()
            || Vessel::where('base_port_id', $port->id)->exists();
        if ($inUse) {
            return back()->with('error', 'No se puede eliminar este terminal porque está vinculado a un tramo, una ruta operativa o una embarcación. Elimina o reasigna primero esos registros.');
        } $port->delete();

        return redirect()->route('admin.ports.index')->with('success', 'Puerto eliminado correctamente.');
    }

    private function formData(array $extra = []): array
    {
        $port = $extra['port'] ?? null;
        $loreto = GeographicDepartment::where('code', '16')->firstOrFail();
        $provinces = ($port?->department_id === $loreto->id ? $port->department->provinces() : $loreto->provinces())->orderBy('name')->get();

        $locationTree = $loreto->provinces()->with(['districts' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get()->map(fn ($province) => [
            'id' => $province->id,
            'name' => $province->name,
            'districts' => $province->districts->map(fn ($district) => ['id' => $district->id, 'name' => $district->name])->values(),
        ])->values();

        return $extra + ['loreto' => $loreto, 'departments' => collect([$loreto]), 'initialProvinces' => $provinces, 'initialDistricts' => $port?->province?->districts()->orderBy('name')->get() ?? collect(), 'locationTree' => $locationTree];
    }

    private function normalized(StorePortRequest $request): array
    {
        $data = $request->validated();
        $loreto = GeographicDepartment::where('code', '16')->firstOrFail();
        $data['department_id'] = $loreto->id;
        $province = GeographicProvince::findOrFail($data['province_id']);
        $district = GeographicDistrict::findOrFail($data['district_id']);
        if ($province->department_id !== $loreto->id || $district->province_id !== (int) $data['province_id']) {
            abort(422, 'La ubicación seleccionada no es válida.');
        } $data['city'] = $district->name;
        $data['region'] = $loreto->name;

        return $data;
    }
}
