<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteHero extends Model
{
    public const FOCAL_POINTS = ['center', 'top', 'bottom', 'left', 'right'];

    protected $fillable = ['page_key', 'public_page_id', 'image_path', 'focal_point', 'alt_text'];

    public function publicPage(): BelongsTo
    {
        return $this->belongsTo(PublicPage::class);
    }
}
