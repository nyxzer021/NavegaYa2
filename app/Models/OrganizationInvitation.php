<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationInvitation extends Model
{
    protected $fillable = ['organization_id', 'email', 'token', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }
}
