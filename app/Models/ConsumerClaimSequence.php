<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumerClaimSequence extends Model
{
    protected $fillable = ['provider_key', 'series_year', 'last_number'];
}
