<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationBanner;
use App\Models\OrganizationPhoto;
use App\Support\AdminScope;
use App\Support\CompanyPublicPresentation;
use App\Support\PhoneNumber;
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
            'colorFields' => CompanyPublicPresentation::colorFields(),
            'brandColors' => CompanyPublicPresentation::brandFieldValues(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate($this->rules(), $this->uploadMessages());
        $data['whatsapp'] = PhoneNumber::peru($data['whatsapp'] ?? null);
        $data['show_public_whatsapp'] = $request->boolean('show_public_whatsapp');
        $data['profile_content_status'] = 'published';
        if ($request->hasFile('logo')) {
            $data['logo_path'] = Storage::url($request->file('logo')->store('company-profiles', 'public'));
        }
        if ($request->hasFile('cover')) {
            $data['cover_image_path'] = Storage::url($request->file('cover')->store('company-profiles', 'public'));
        }
        unset($data['logo'], $data['cover']);
        if ($this->presentationWasSent($request)) {
            $data['public_presentation'] = $this->presentation($data);
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
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'whatsapp_message' => ['nullable', 'string', 'max:500'],
            'service_hours' => ['nullable', 'string', 'max:180'],
            'show_public_whatsapp' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:500'],
            'address' => ['nullable', 'string', 'max:250'],
            'public_description' => ['nullable', 'string', 'max:1500'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'cover' => ['nullable', 'image', 'max:5120'],
            'cargo_pequeno' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'cargo_mediano' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'cargo_grande' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'cargo_port_note' => ['nullable', 'string', 'max:180'],
        ];
        foreach (array_keys(CompanyPublicPresentation::brandFieldValues()) as $field) {
            $rules[$field] = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    private function presentationKeys(): array
    {
        return array_merge(array_keys(CompanyPublicPresentation::brandFieldValues()), [
            'cargo_pequeno', 'cargo_mediano', 'cargo_grande', 'cargo_port_note',
        ]);
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
    private function presentation(array $data): array
    {
        $tabs = [];
        foreach (['informacion', 'galeria', 'flota', 'salidas', 'opiniones'] as $tab) {
            $tabs[$tab] = [
                'bg' => $this->hexOrNull($data['tab_'.$tab.'_bg'] ?? null),
                'border' => $this->hexOrNull($data['tab_'.$tab.'_border'] ?? null),
                'text' => $this->hexOrNull($data['tab_'.$tab.'_text'] ?? null),
            ];
        }

        return [
            'menu' => [
                'bar' => $this->hexOrNull($data['menu_bar'] ?? null),
                'stroke' => $this->hexOrNull($data['menu_stroke'] ?? null),
                'tabs' => $tabs,
            ],
            'cargo' => [
                'pequeno' => $this->priceOrNull($data['cargo_pequeno'] ?? null),
                'mediano' => $this->priceOrNull($data['cargo_mediano'] ?? null),
                'grande' => $this->priceOrNull($data['cargo_grande'] ?? null),
                'port_note' => trim((string) ($data['cargo_port_note'] ?? '')) ?: null,
            ],
        ];
    }

    private function hexOrNull(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtolower(trim($value));
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
