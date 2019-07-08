<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceUser\Token;

use App\Rules\AccessCodeValid;
use App\Rules\UkPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
            'phone' => [
                'required',
                new UkPhoneNumber(),
            ],
            'access_code' => [
                'required',
                'string',
                'size:5',
                new AccessCodeValid($this->phone),
            ],
        ];
    }
}
