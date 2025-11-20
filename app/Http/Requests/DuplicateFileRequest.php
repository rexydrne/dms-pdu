<?php

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DuplicateFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $file = File::find($this->input('file_id'));

        if (!$file) {
            return false;
        }

        return $file->isOwnedBy(Auth::id()) ||
               $file->shareables()->where('shared_to', Auth::id())->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file_id' => [
                'required',
                Rule::exists('files', 'id')
                    ->whereNull('deleted_at')
            ],
        ];
    }
}
