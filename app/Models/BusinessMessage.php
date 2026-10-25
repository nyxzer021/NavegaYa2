<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessMessage extends Model
{
    protected $fillable = ['business_conversation_id', 'sender_id', 'body'];
    public function conversation() { return $this->belongsTo(BusinessConversation::class, 'business_conversation_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }
}
