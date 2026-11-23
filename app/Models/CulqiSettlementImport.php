<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CulqiSettlementImport extends Model
{
    protected $fillable = ['source', 'file_hash', 'filename', 'user_id', 'row_count'];

    public function lines(): HasMany
    {
        return $this->hasMany(CulqiSettlementLine::class, 'import_id');
    }
}
