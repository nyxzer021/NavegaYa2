<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicPage extends Model
{
    protected $fillable = ['title', 'title_en', 'slug', 'summary', 'summary_en', 'content', 'content_en', 'is_published'];

    public function localized(string $field): ?string
    {
        $spanish = $this->getAttribute($field);

        return app()->getLocale() === 'en' ? ($this->getAttribute($field.'_en') ?: $spanish) : $spanish;
    }

    public function isSpanishFallback(): bool
    {
        return app()->getLocale() === 'en' && blank($this->title_en) && blank($this->content_en);
    }

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
