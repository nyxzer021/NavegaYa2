<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StorePortRequest;
use App\Models\GeographicDepartment;
use App\Models\GeographicDistrict;
use App\Models\GeographicProvince;
use App\Models\OrganizationSite;
use App\Models\Port;
use App\Models\Vessel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminPortController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.ports.index', $this->formData([
            'ports' => Port::with(['department', 'province', 'district'])
                ->withCount(['originMasterRoutes', 'destinationMasterRoutes', 'originRoutes', 'destinationRoutes', 'baseVessels', 'baseOrganizations', 'organizationSites'])
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$request->string('search').'%')->orWhere('city', 'like', '%'.$request->string('search').'%')))
                ->when($request->filled('modality'), fn ($query) => $query->where('modality', $request->string('modality')))
                ->orderBy('modality')->orderBy('city')->orderBy('name')->get(),
            'repeatedPortLabels' => $this->repeatedPortLabels(),
        ]));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.ports.index', ['nuevo' => 1])->withFragment('nuevo-puerto');
    }

    public function store(StorePortRequest $request): RedirectResponse
    {
        $port = Port::create($this->normalized($request));

        return $this->saved($port, 'Puerto registrado correctamente.');
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
        $data = $this->normalized($request);
        if ($request->exists('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }
        if ($port->is_active && array_key_exists('is_active', $data) && $data['is_active'] === false && $this->assignedAsBase($port)) {
            return back()->with('error', 'Este puerto es la base de una empresa o de una sede. Reasígnalo antes de desactivarlo.');
        }
        $port->update($data);

        return $this->saved($port, 'Puerto actualizado correctamente.');
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
            || Vessel::where('base_port_id', $port->id)->exists()
            || $this->assignedAsBase($port);
        if ($inUse) {
            return back()->with('error', 'No se puede eliminar este terminal porque está vinculado a un tramo, una ruta, una embarcación o es el puerto base de una empresa. Reasígnalo primero.');
        } $port->delete();

        return redirect()->route('admin.ports.index')->with('success', 'Puerto eliminado correctamente.');
    }

    private function assignedAsBase(Port $port): bool
    {
        return $port->baseOrganizations()->exists() || OrganizationSite::query()->where('port_id', $port->id)->exists();
    }

    private function saved(Port $port, string $success): RedirectResponse
    {
        $port->load('district');
        $others = Port::query()->with('district')->whereKeyNot($port->id)->get()
            ->filter(fn (Port $other): bool => mb_strtolower(trim($other->name)) === mb_strtolower(trim($port->name)))
            ->map(fn (Port $other): string => $other->optionLabel())
            ->values();
        $redirect = redirect()->route('admin.ports.index')->with('success', $success);
        if ($others->isNotEmpty()) {
            $redirect->with('warning', 'Se guardó '.$port->optionLabel().'. Ya existe otro terminal con el mismo nombre: '.$others->join(', ').'.');
        }

        return $redirect;
    }

    /**
     * @return Collection<int, string>
     */
    private function repeatedPortLabels(): Collection
    {
        return Port::query()->with('district')->orderBy('name')->get()
            ->groupBy(fn (Port $port): string => mb_strtolower(trim($port->name)))
            ->flatMap(fn ($group) => $group->count() > 1 ? $group->map(fn (Port $port): string => $port->optionLabel()) : []);
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
