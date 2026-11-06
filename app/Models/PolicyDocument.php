<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyDocument extends Model
{
    protected $fillable = ['kind', 'organization_id', 'title'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PolicyVersion::class);
    }

    public function current(): ?PolicyVersion
    {
        return $this->versions()->whereNotNull('published_at')->orderByDesc('version')->first();
    }
}
