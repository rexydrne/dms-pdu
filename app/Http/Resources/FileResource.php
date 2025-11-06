<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->is_folder ? 'Folder' : strtoupper(pathinfo($this->name, PATHINFO_EXTENSION));
        if (!$this->is_folder && empty($type)) {
            $type = explode('/', $this->mime)[1] ?? 'File';
        }

        return [
            "id" => $this->id,
            "name" => $this->name,
            "path" => $this->path,
            "storage_path" => $this->storage_path,
            "url" => $this->is_folder
                ? null
                : route('file.view', ['fileId' => $this->id]),
            "parent_id" => $this->parent_id,
            "is_folder" => $this->is_folder,
            // "mime" => $this->mime,
            "size" => $this->get_file_size(),
            "type" => $type,
            "owner" => $this->owner,
            "created_at" => $this->created_at->diffForHumans(),
            "updated_at" => $this->updated_at->diffForHumans(),
            "created_by" => $this->created_by,
            "updated_by" => $this->updated_by,
            "deleted_at" => $this->deleted_at,

            "labels" => $this->whenLoaded('labels', function () {
                return $this->labels->map(function ($label) {
                    return [
                        'id' => $label->id,
                        'name' => $label->name,
                        'color' => $label->color,
                    ];
                });
            }),
        ];
    }
}
