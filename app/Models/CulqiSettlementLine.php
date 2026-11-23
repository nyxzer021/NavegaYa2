<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CulqiSettlementLine extends Model
{
    protected $fillable = ['import_id', 'kind', 'provider_reference', 'occurred_on', 'amount', 'fee', 'net'];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'net' => 'decimal:2',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(CulqiSettlementImport::class, 'import_id');
    }
}
