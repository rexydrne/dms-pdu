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

    /**
     * @OA\Get(
     *     path="/api/recommended-files",
     *     summary="Get recommended files based on access frequency",
     *     tags={"Files"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         required=false,
     *         description="Sort parameter",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="recommended_files", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
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
