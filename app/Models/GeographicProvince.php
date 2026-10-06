<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeographicProvince extends Model
{
    protected $table = 'geo_provinces';

    protected $fillable = ['department_id', 'code', 'name'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(GeographicDepartment::class, 'department_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(GeographicDistrict::class, 'province_id');
    }
}
