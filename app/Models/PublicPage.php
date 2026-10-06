<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicPage extends Model
{
    protected $fillable = ['title', 'slug', 'summary', 'content', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
