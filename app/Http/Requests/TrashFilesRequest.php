<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TrashFilesRequest extends BaseMassActionRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'ids.*' => [
                'integer',
                Rule::exists('files', 'id')->where(function ($query) {
                    $query->where('created_by', Auth::id());
                })
            ]
        ]);
    }
}
