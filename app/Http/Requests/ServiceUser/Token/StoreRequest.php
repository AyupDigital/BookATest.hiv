<?php

namespace App\Http\Requests\ServiceUser\Token;

use App\Rules\AccessCodeValid;
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
            'access_code' => [
                'required',
                'string',
                'size:5',
                new AccessCodeValid(),
            ],
        ];
    }
}