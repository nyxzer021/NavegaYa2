<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanyProvisioningService
{
    public function submitApplication(array $data): Organization
    {
        return DB::transaction(fn (): Organization => Organization::create([
            'type' => 'transport_company',
            'legal_name' => $data['legal_name'],
            'commercial_name' => $data['commercial_name'] ?? null,
            'contact_name' => $data['contact_name'],
            'ruc' => $data['ruc'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'address' => $data['address'],
            'website' => $data['website'] ?? null,
            'modality' => $data['modality'],
            'base_city' => $data['base_city'],
            'commission_rate' => 8,
            'status' => 'pending',
        ]));
    }

    public function provision(array $data, bool $isAdminInitiated = false): array
    {
        return DB::transaction(function () use ($data, $isAdminInitiated): array {
            $status = $isAdminInitiated ? 'active' : 'pending';
            $organization = Organization::create([
                'type' => $data['type'] ?? 'transport_company',
                'legal_name' => $data['company_name'] ?? $data['legal_name'],
                'commercial_name' => $data['commercial_name'] ?? null,
                'contact_name' => $data['admin_name'] ?? $data['contact_name'],
                'ruc' => $data['ruc'],
                'email' => $data['admin_email'] ?? $data['email'],
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'address' => $data['address'] ?? null,
                'website' => $data['website'] ?? null,
                'modality' => $data['modality'] ?? 'fluvial',
                'base_city' => $data['base_city'] ?? 'Iquitos',
                'commission_rate' => $data['commission_rate'] ?? 8.00,
                'status' => $status,
                'verified_at' => $isAdminInitiated ? now() : null,
                'contact_verified_at' => $isAdminInitiated ? now() : null,
            ]);
            $user = User::create([
                'name' => $data['admin_name'] ?? $data['contact_name'],
                'email' => $data['admin_email'] ?? $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['admin_password']),
                'email_verified_at' => $isAdminInitiated ? now() : null,
            ]);
            $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador de empresa']);
            $user->organizations()->attach($organization->id, ['status' => $status]);
            $user->roles()->attach($role->id, ['organization_id' => $organization->id]);

            return [$organization, $user];
        });
    }
}
