<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PublicPage extends Model
{
    protected $fillable = ['title', 'slug', 'summary', 'content', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function hero(): HasOne
    {
        return $this->hasOne(SiteHero::class);
    }
}
