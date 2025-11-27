<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\FileAccessLog;
use App\Http\Resources\FileResource;
use Illuminate\Support\Facades\Auth;

class LastOpenedController extends Controller
{
    public function lastOpenedFiles()
    {
        $userId = Auth::id();

        try {
            $files = File::query()
                ->with('labels')
                ->join('file_access_logs as log', 'log.file_id', '=', 'files.id')
                ->where('log.user_id', $userId)
                ->whereNull('files.deleted_at')
                ->whereNotNull('files.parent_id')
                ->select('files.*', 'log.last_accessed_at')
                ->orderBy('log.last_accessed_at', 'desc')
                ->limit(12)
                ->get();

            $lastOpenedFolders = $files->where('is_folder', true)->take(6);
            $lastOpenedFiles = $files->where('is_folder', false)->take(6);

            $message = $files->isNotEmpty()
                ? 'Successfully retrieved last opened files and folders.'
                : 'No recent access logs found.';

            return response()->json([
                'success'             => true,
                'message'             => $message,
                'last_opened_folders' => FileResource::collection($lastOpenedFolders),
                'last_opened_files'   => FileResource::collection($lastOpenedFiles),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve last opened files.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
