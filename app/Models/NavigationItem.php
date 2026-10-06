<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationItem extends Model
{
    protected $fillable = ['label', 'label_en', 'target_type', 'target', 'position', 'is_active', 'opens_new_tab'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'opens_new_tab' => 'boolean', 'position' => 'integer'];
    }

    public function url(): string
    {
        return match ($this->target_type) {
            'route' => \Route::has($this->target) ? route($this->target) : '#','page' => route('pages.show', $this->target),default => $this->target
        };
    }

    public function text(): string
    {
        return app()->getLocale() === 'en' && $this->label_en ? $this->label_en : $this->label;
    }
}
