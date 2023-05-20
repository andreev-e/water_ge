<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriptions extends Model
{
    protected $fillable = [
        'bot_user_id',
        'service_center_id',
    ];

    public function botUser()
    {
        return $this->belongsTo(BotUser::class);
    }
}
