<?php

namespace App\Helpers;

use App\Models\File;

class FileHelper
{
    public static function generateUniqueName($name, $parentId, $userId)
    {
        $originalName = $name;
        $extension = '';

        if (str_contains($name, '.')) {
            $extension = '.' . pathinfo($name, PATHINFO_EXTENSION);
            $originalName = pathinfo($name, PATHINFO_FILENAME);
        }

        $counter = 1;
        $newName = $name;

        while (
            File::where('parent_id', $parentId)
                ->where('created_by', $userId)
                ->where('name', $newName)
                ->exists()
        ) {
            $newName = $originalName . " ($counter)" . $extension;
            $counter++;
        }

        return $newName;
    }
}
