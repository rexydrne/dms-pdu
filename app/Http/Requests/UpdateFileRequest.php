<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $fileId = $this->route('fileId');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:files,name,' . $fileId . ',id,parent_id,' . $this->file->parent_id . ',deleted_at,NULL',
            ],
            'label_ids' => 'nullable|array',
            'label_ids.*' => 'exists:labels,id',
        ];
    }

    /**
     * Prepare the data for validation.
     * * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('fileId')) {
            $this->merge(['file' => \App\Models\File::findOrFail($this->route('fileId'))]);
        }
    }

    /**
     * Set the file model instance on the request after validation.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
        });
    }
}
