<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Http\Resources\FileResource;
use Illuminate\Support\Facades\Auth;

class RecommendedController extends Controller
{
    public function recommendedFiles()
    {
        $userId = Auth::id();

        try {
            $recommendedFiles = File::query()
                ->select('files.*', \DB::raw('COUNT(log.id) as access_count'))
                ->join('file_access_logs as log', 'log.file_id', '=', 'files.id')
                ->where('files.created_by', $userId)
                ->where('log.user_id', $userId)
                ->where('files.is_folder', false)
                ->whereNull('files.deleted_at')
                ->groupBy('files.id')
                ->orderByDesc('access_count')
                ->orderByDesc('files.updated_at')
                ->limit(5)
                ->with('labels')
                ->get();

            $message = $recommendedFiles->isNotEmpty()
                ? 'Successfully retrieved recommended files.'
                : 'No sufficient access data to generate recommendations.';

            return response()->json([
                'success'             => true,
                'message'             => $message,
                'recommended_files'   => FileResource::collection($recommendedFiles),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recommended files.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
