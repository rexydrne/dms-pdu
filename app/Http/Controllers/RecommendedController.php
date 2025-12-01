<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Http\Resources\FileResource;
use Illuminate\Support\Facades\Auth;
use App\Traits\SortableFileQuery;
use App\Traits\FilterableFileQuery;

class RecommendedController extends Controller
{
    use SortableFileQuery, FilterableFileQuery;

    public function recommendedFiles(Request $request)
    {
        $userId = Auth::id();
        $sortBy = $request->get('sort');

        try {
            $query = File::query()
                ->select('files.*', \DB::raw('COUNT(log.id) as access_count'))
                ->join('file_access_logs as log', 'log.file_id', '=', 'files.id')
                ->where('files.created_by', $userId)
                ->where('log.user_id', $userId)
                ->where('files.is_folder', false)
                ->whereNull('files.deleted_at')
                ->groupBy('files.id')
                ->with('labels');

            $query = $this->applyDmsFiltering($query, $request);

            // primary sort: most accessed first
            $query->orderByDesc('access_count');

            // secondary sort: user requested sort
            $secondarySortRule = $this->getSortRule($sortBy);
            if ($secondarySortRule) {
                $query->orderBy($secondarySortRule['column'], $secondarySortRule['direction']);
            }
            $query->orderByDesc('files.updated_at');

            $recommendedFiles = $query->limit(5)->get();

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
