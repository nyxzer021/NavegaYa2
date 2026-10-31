<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DestinationCity extends Model
{
    protected $fillable = ['name', 'department', 'river', 'summary', 'summary_en', 'attractions', 'attractions_en', 'lodging', 'lodging_en', 'gastronomy', 'gastronomy_en', 'image_url', 'image_alt', 'image_alt_en', 'sort_order', 'is_active', 'show_in_discover'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_in_discover' => 'boolean'];
    }
}
