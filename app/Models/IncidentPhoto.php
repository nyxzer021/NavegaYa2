<?php

namespace App\Models;

use App\Support\StoredFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentPhoto extends Model
{
    protected $fillable = ['departure_incident_id', 'path', 'original_name'];

    protected static function booted(): void
    {
        static::deleting(function (self $photo): void {
            StoredFiles::deleteDocument($photo->path);
        });
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(DepartureIncident::class, 'departure_incident_id');
    }
}
