<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStat extends Model
{
    protected $primaryKey = 'date';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'date',
        'users',
        'subscriptions',
        'filtered',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
