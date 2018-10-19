<?php

namespace App\Http\Requests\Appointment;

use App\Rules\CalendarFeedTokenIsValid;
use Illuminate\Foundation\Http\FormRequest;

class IcsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'calendar_feed_token' => [
                'required',
                new CalendarFeedTokenIsValid(),
            ],
        ];
    }
}
