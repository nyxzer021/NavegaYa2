<?php

namespace App\Support;

use App\Models\User;

class PortalHome
{
    /**
     * @return array{url: string, label: string}
     */
    public static function for(?User $user): array
    {
        $roles = $user?->roles->pluck('code') ?? collect();

        return match (true) {
            $roles->contains('company_counter') => ['url' => route('company.counter.index'), 'label' => 'Volver a mi panel'],
            $roles->contains('company_admin') => ['url' => route('company.dashboard'), 'label' => 'Volver a mi panel'],
            $roles->contains('marketplace_admin') => ['url' => route('marketplace.dashboard'), 'label' => 'Volver a mi panel'],
            $roles->intersect(['super_admin', 'admin'])->isNotEmpty() => ['url' => route('admin.dashboard'), 'label' => 'Volver a mi panel'],
            $user !== null => ['url' => route('travels.index'), 'label' => 'Volver a mi panel'],
            default => ['url' => route('home'), 'label' => 'Volver al inicio'],
        };
    }
}
