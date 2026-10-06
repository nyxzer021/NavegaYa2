<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeHeroSlide extends Model
{
    protected $fillable = ['image_path', 'title', 'subtitle', 'button_label', 'button_url', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
