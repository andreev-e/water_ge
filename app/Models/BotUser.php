<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotUser extends Model
{
    protected $table = 'bot_user';

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscriptions::class);
    }
}
