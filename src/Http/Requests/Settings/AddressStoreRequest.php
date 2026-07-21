<?php

namespace EQ\LaravelEcommerce\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class AddressStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'city' => [
                'required',
                'string',
            ],
            'country' => [
                'required',
                'string',
            ],
            'line_1' => [
                'required',
                'string',
            ],
            'line_2' => [
                'sometimes',
                'string',
                'nullable'
            ],
            'state' => [
                'required',
                'string',
            ],
            // 'zip_code' => [
            // 'string',
            // ],
        ];
    }
}
