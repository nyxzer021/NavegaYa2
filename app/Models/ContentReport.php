<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentReport extends Model
{
    protected $fillable = ['reporter_id', 'content_type', 'content_id', 'reason', 'details', 'priority', 'status', 'resolution', 'moderator_notes', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
