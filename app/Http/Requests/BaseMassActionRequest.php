<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BaseMassActionRequest extends BaseFileRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'all' => 'nullable|bool',
            'ids' => 'required_without:all|array',
            'ids.*' => 'integer|distinct',
        ]);
    }

    public function messages(): array
    {
        return [
            'ids.required_without' => 'Either "all" must be true or a list of "ids" must be provided.',
        ];
    }
}
