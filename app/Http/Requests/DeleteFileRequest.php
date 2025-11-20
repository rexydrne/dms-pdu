<?php

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DeleteFileRequest extends ParentIdBaseRequest
{
     /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'file_id' => $this->route('fileId')
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file_id' => [
                'required',
                Rule::exists('files', 'id')->whereNull('deleted_at'),

                function ($attribute, $id, $fail) {
                    $file = File::find($id);

                    if (!$file) {
                        $fail('File not found (ID: "' . $id . '")');
                        return;
                    }

                    if (!$file->isOwnedBy(Auth::id())) {
                        $fail('You can only delete files that you own (ID: "' . $id . '")');
                    }
                }
            ]
        ];
    }
}
