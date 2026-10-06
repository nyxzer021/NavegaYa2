<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DestinationCity extends Model
{
    protected $fillable = ['name', 'department', 'river', 'summary', 'attractions', 'lodging', 'gastronomy', 'image_url', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
