<?php

namespace App\Models;

use App\Support\LimaBusinessCalendar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumerClaim extends Model
{
    protected $fillable = [
        'provider', 'provider_key', 'organization_id', 'reservation_id', 'reservation_code',
        'series_year', 'sequence', 'code', 'public_token', 'filed_on', 'due_on',
        'consumer_name', 'document_type', 'document_number', 'address', 'phone', 'email',
        'is_minor', 'guardian_name', 'guardian_document', 'guardian_relationship',
        'good_kind', 'amount', 'good_description', 'claim_type', 'detail', 'request_text',
        'email_accepted', 'response_text', 'responded_at', 'responded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'filed_on' => 'date',
            'due_on' => 'date',
            'amount' => 'decimal:2',
            'is_minor' => 'boolean',
            'email_accepted' => 'boolean',
            'responded_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }

    public function answered(): bool
    {
        return $this->responded_at !== null;
    }

    /**
     * @return array{days: int, overdue: bool, tone: string}
     */
    public function countdown(): array
    {
        return app(LimaBusinessCalendar::class)->countdown($this->due_on->toDateString());
    }
}
