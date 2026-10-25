<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id', 'organization_id', 'responded_by', 'code', 'requester_type', 'category', 'priority',
        'status', 'subject', 'description', 'admin_response', 'responded_at', 'requester_viewed_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'requester_viewed_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
