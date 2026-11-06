<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyReview extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'reservation_id', 'rating', 'comment', 'status',
        'company_response', 'company_responded_at', 'moderation_reason', 'moderated_by', 'moderated_at',
    ];

    protected function casts(): array
    {
        return [
            'company_responded_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
