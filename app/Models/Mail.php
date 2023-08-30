<?php

namespace App\Models;

use App\Enums\MailStatuses;
use App\Notifications\EventNotification;
use App\Notifications\MailNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class Mail extends Model
{
    protected $fillable = [
        'text',
        'to',
        'status',
    ];

    protected $casts = [
        'to' => 'array',
    ];


    public function send(): void
    {
        foreach ($this->to as $id) {
            Notification::route('telegram', $id)
                ->notify(new MailNotification($this));
        }
        $this->status = MailStatuses::sent;
        $this->save();
    }
}
