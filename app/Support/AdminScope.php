<?php

namespace App\Support;

use App\Models\User;

class AdminScope
{
    public static function organizationId(User $user): ?int
    {
        if ($user->roles()->where('code', 'super_admin')->exists()) {
            return null;
        }

        return $user->organizations()
            ->whereIn('organizations.status', ['active', 'pending'])
            ->orderByRaw("case organizations.status when 'active' then 0 else 1 end")
            ->value('organizations.id');
    }
}
