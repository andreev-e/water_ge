<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dictionary extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'name_ru',
    ];

    public $timestamps = false;
}
