<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeographicDistrict extends Model
{
    protected $table = 'geo_districts';

    protected $fillable = ['province_id', 'code', 'name'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(GeographicProvince::class, 'province_id');
    }
}
