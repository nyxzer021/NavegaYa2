<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationModality extends Model
{
    protected $fillable = [
        'organization_id', 'code', 'status', 'permit_scope', 'permit_number', 'permit_expires_on',
        'permit_path', 'permit_original_name', 'aoc_number', 'aoc_expires_on', 'aoc_path', 'aoc_original_name',
        'rejection_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'permit_expires_on' => 'date',
            'aoc_expires_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function label(): string
    {
        return __('company_portal.modality.'.$this->code);
    }
}
