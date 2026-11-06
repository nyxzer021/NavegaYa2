<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyVersion extends Model
{
    protected $fillable = [
        'policy_document_id', 'version', 'summary', 'body_html', 'effective_on',
        'published_at', 'edited_by', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PolicyDocument::class, 'policy_document_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
