<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessConversation extends Model
{
    protected $fillable = ['organization_id', 'subject', 'status', 'admin_last_read_at', 'business_last_read_at'];
    protected $casts = ['admin_last_read_at' => 'datetime', 'business_last_read_at' => 'datetime'];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function messages() { return $this->hasMany(BusinessMessage::class); }
}
