<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptSequence extends Model
{
    protected $fillable = ['organization_id', 'series_year', 'last_number'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
