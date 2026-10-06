<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckoutOrder extends Model
{
    protected $fillable = ['user_id', 'code', 'contact_name', 'contact_email', 'contact_phone', 'subtotal_amount', 'platform_fee_amount', 'total_amount', 'platform_fee_percent', 'status', 'expires_at'];

    protected function casts(): array
    {
        return ['subtotal_amount' => 'decimal:2', 'platform_fee_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'platform_fee_percent' => 'decimal:2', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
