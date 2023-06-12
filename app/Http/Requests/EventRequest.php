<?php

namespace App\Http\Requests;

use App\Enums\EventTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class EventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'service_center_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', new Enum(EventTypes::class)],
        ];
    }
}
