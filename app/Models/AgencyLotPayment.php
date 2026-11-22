<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyLotPayment extends Model
{
    protected $fillable = [
        'organization_id', 'agency_lot_id', 'amount', 'paid_on', 'method', 'operation_number',
        'note', 'status', 'void_reason', 'voided_at', 'voided_by_user_id', 'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AgencyLot::class, 'agency_lot_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }
}
