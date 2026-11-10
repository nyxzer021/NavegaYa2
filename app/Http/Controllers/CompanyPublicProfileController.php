<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationBanner;
use App\Models\OrganizationPhoto;
use App\Support\AdminScope;
use App\Support\CompanyPublicPresentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompanyPublicProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $organization = $this->organization($request)->load('photos');

        return view('company.public-profile', [
            'organization' => $organization,
            'presentation' => CompanyPublicPresentation::forOrganization($organization),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate($this->rules(), $this->uploadMessages());
        $data['profile_content_status'] = 'published';
        if ($request->hasFile('logo')) {
            $data['logo_path'] = Storage::url($request->file('logo')->store('company-profiles', 'public'));
        }
        unset($data['logo']);
        if ($this->presentationWasSent($request)) {
            $data['public_presentation'] = $this->presentation($data, $organization);
        }
        foreach ($this->presentationKeys() as $key) {
            unset($data[$key]);
        }
        $organization->update($data);

        return back()->with('success', 'La ficha pública quedó actualizada.');
    }

    public function storePhotos(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'photos' => ['required', 'array', 'max:8'],
            'photos.*' => ['image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:120'],
        ], $this->uploadMessages());
        $next = (int) $organization->photos()->max('sort_order');
        foreach ($data['photos'] as $photo) {
            $next++;
            $organization->photos()->create([
                'path' => Storage::url($photo->store('company-profiles', 'public')),
                'caption' => $data['caption'] ?? null,
                'alt_text' => $data['caption'] ?? null,
                'status' => 'pending',
                'sort_order' => $next,
            ]);
        }
        $organization->update(['profile_content_status' => 'published']);

        return back()->with('success', 'Las fotos quedaron pendientes de revisión. No se publican solas.');
    }

    public function storeBanner(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'banner' => ['required', 'image', 'mimes:webp,jpg,jpeg,png', 'max:5120'],
            'focal_x' => ['nullable', 'numeric', 'between:0,100'],
            'focal_y' => ['nullable', 'numeric', 'between:0,100'],
        ], [
            'banner.max' => 'La portada no puede pasar de 5 MB.',
            'banner.image' => 'La portada debe ser una imagen.',
        ]);
        if ($organization->banners()->count() >= 2) {
            return back()->withErrors(['banner' => 'Solo puedes publicar dos portadas.']);
        }
        $position = (int) $organization->banners()->max('position') + 1;
        $organization->banners()->create([
            'path' => $this->storeBannerFile($request->file('banner')),
            'focal_x' => $data['focal_x'] ?? 50,
            'focal_y' => $data['focal_y'] ?? 50,
            'position' => $position,
        ]);

        return back()->with('success', 'La portada quedó publicada.');
    }

    public function destroyBanner(Request $request, OrganizationBanner $banner): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless((int) $banner->organization_id === (int) $organization->id, 404);
        $banner->delete();

        return back()->with('success', 'La portada se quitó.');
    }

    public function updateBannerFocus(Request $request, OrganizationBanner $banner): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless((int) $banner->organization_id === (int) $organization->id, 404);
        $data = $request->validate([
            'focal_x' => ['required', 'numeric', 'between:0,100'],
            'focal_y' => ['required', 'numeric', 'between:0,100'],
        ]);
        $banner->update([
            'focal_x' => round((float) $data['focal_x'], 2),
            'focal_y' => round((float) $data['focal_y'], 2),
        ]);

        return back()->with('success', trans('company_portal.focus.saved', [], 'es'));
    }

    public function destroyPhoto(Request $request, OrganizationPhoto $photo): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless((int) $photo->organization_id === (int) $organization->id, 404);
        $photo->delete();

        return back()->with('success', 'La foto se quitó de la galería.');
    }

    /**
     * @return array<string, string>
     */
    private function uploadMessages(): array
    {
        return [
            'logo.uploaded' => 'No se pudo subir el logo. Usa un JPG o PNG de hasta 5 MB.',
            'logo.image' => 'El logo debe ser una imagen JPG o PNG.',
            'logo.max' => 'El logo no puede pasar de 5 MB.',
            'cover.uploaded' => 'No se pudo subir la portada. Usa un JPG o PNG de hasta 5 MB.',
            'cover.image' => 'La portada debe ser una imagen JPG o PNG.',
            'cover.max' => 'La portada no puede pasar de 5 MB.',
            'photos.required' => 'Elige al menos una foto para la galería.',
            'photos.max' => 'Puedes subir hasta 8 fotos a la vez.',
            'photos.*.uploaded' => 'No se pudo subir una foto. Cada imagen debe ser JPG o PNG de hasta 5 MB.',
            'photos.*.image' => 'Cada foto debe ser una imagen JPG o PNG.',
            'photos.*.max' => 'Cada foto no puede pasar de 5 MB.',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        $rules = [
            'commercial_name' => ['nullable', 'string', 'max:150'],
            'service_hours' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:500'],
            'address' => ['nullable', 'string', 'max:250'],
            'public_description' => ['nullable', 'string', 'max:1500'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'cargo_pequeno' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'cargo_mediano' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'cargo_grande' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'cargo_port_note' => ['nullable', 'string', 'max:180'],
        ];

        return $rules;
    }

    /**
     * @return list<string>
     */
    private function presentationKeys(): array
    {
        return ['cargo_pequeno', 'cargo_mediano', 'cargo_grande', 'cargo_port_note'];
    }

    private function presentationWasSent(Request $request): bool
    {
        foreach ($this->presentationKeys() as $key) {
            if ($request->exists($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function presentation(array $data, Organization $organization): array
    {
        $stored = is_array($organization->public_presentation) ? $organization->public_presentation : [];
        $payload = [
            'cargo' => [
                'pequeno' => $this->priceOrNull($data['cargo_pequeno'] ?? null),
                'mediano' => $this->priceOrNull($data['cargo_mediano'] ?? null),
                'grande' => $this->priceOrNull($data['cargo_grande'] ?? null),
                'port_note' => trim((string) ($data['cargo_port_note'] ?? '')) ?: null,
            ],
        ];
        if (is_array($stored['menu'] ?? null)) {
            $payload['menu'] = $stored['menu'];
        }

        return $payload;
    }

    private function priceOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function storeBannerFile(UploadedFile $file): string
    {
        $binary = file_get_contents($file->getRealPath());
        $image = function_exists('imagecreatefromstring') && function_exists('imagewebp') ? @imagecreatefromstring((string) $binary) : false;
        if ($image === false) {
            return Storage::url($file->store('company-banners', 'public'));
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = min(1920, $width);
        $targetHeight = (int) round($height * ($targetWidth / max(1, $width)));
        if ($targetHeight > 480) {
            $targetHeight = 480;
            $targetWidth = max(1, (int) round($width * (480 / max(1, $height))));
        }
        $canvas = imagecreatetruecolor(max(1, $targetWidth), max(1, $targetHeight));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, imagesx($canvas), imagesy($canvas), $width, $height);
        $relative = 'company-banners/'.Str::uuid().'.webp';
        $absolute = storage_path('app/public/'.$relative);
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }
        imagewebp($canvas, $absolute, 82);
        imagedestroy($image);
        imagedestroy($canvas);

        return Storage::url($relative);
    }

    public function usePhotoAsCover(Request $request, OrganizationPhoto $photo): JsonResponse|RedirectResponse
    {
        $organization = $this->ownedPhoto($request, $photo);
        $banners = $organization->banners()->orderBy('position')->get();
        $replaceId = $request->integer('replace_id');
        if ($banners->count() >= 2 && $replaceId === 0) {
            return response()->json([
                'choose' => true,
                'banners' => $banners->map(fn (OrganizationBanner $banner): array => [
                    'id' => $banner->id,
                    'position' => $banner->position,
                    'path' => $banner->path,
                ])->values(),
            ], 409);
        }

        if ($replaceId > 0) {
            $banner = $banners->firstWhere('id', $replaceId);
            abort_unless($banner, 404);
            $banner->update(['path' => $photo->path, 'position' => 0]);
        } else {
            $organization->banners()->increment('position');
            $organization->banners()->create([
                'path' => $photo->path,
                'focal_x' => 50,
                'focal_y' => 50,
                'position' => 0,
            ]);
        }

        $ordered = $organization->banners()->orderBy('position')->orderBy('id')->get()->values();
        if ($ordered->count() > 2) {
            $ordered->slice(2)->each->delete();
            $ordered = $ordered->take(2)->values();
        }
        foreach ($ordered as $index => $banner) {
            $banner->update(['position' => $index + 1]);
        }

        $payload = [
            'banners' => $organization->banners()->orderBy('position')->get(['id', 'path', 'position'])->map(fn (OrganizationBanner $banner): array => [
                'id' => $banner->id,
                'path' => $banner->path,
                'position' => $banner->position,
            ])->values(),
        ];

        return $request->expectsJson()
            ? response()->json($payload)
            : back()->with('success', 'La foto quedó como primera portada.');
    }

    public function updatePhotoCaption(Request $request, OrganizationPhoto $photo): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless((int) $photo->organization_id === (int) $organization->id, 403);
        $data = $request->validate(
            ['caption' => ['nullable', 'string', 'max:120']],
            ['caption.max' => 'El título no puede pasar de 120 caracteres.'],
        );
        $caption = trim((string) ($data['caption'] ?? ''));
        $photo->update(['caption' => $caption === '' ? null : $caption]);

        return back()->with('success', 'El título de la foto quedó guardado.');
    }

    public function usePhotoAsLogo(Request $request, OrganizationPhoto $photo): JsonResponse|RedirectResponse
    {
        $organization = $this->ownedPhoto($request, $photo);
        $organization->update(['logo_path' => $photo->path]);

        return $request->expectsJson()
            ? response()->json(['logo' => $photo->path])
            : back()->with('success', 'La foto quedó como logo.');
    }

    private function ownedPhoto(Request $request, OrganizationPhoto $photo): Organization
    {
        $organization = $this->organization($request);
        abort_unless((int) $photo->organization_id === (int) $organization->id && $photo->status === 'published', 403);

        return $organization;
    }

    private function organization(Request $request): Organization
    {
        abort_unless($request->user()->roles()->where('code', 'company_admin')->exists(), 403);

        return Organization::query()
            ->whereKey(AdminScope::organizationId($request->user()))
            ->where('type', 'transport_company')
            ->whereIn('status', ['active', 'pending'])
            ->firstOrFail();
    }
}
