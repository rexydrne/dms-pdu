<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\FileAccessLog;
use App\Http\Resources\FileResource;
use Illuminate\Support\Facades\Auth;
use App\Traits\SortableFileQuery;
use App\Traits\FilterableFileQuery;

class LastOpenedController extends Controller
{
    use SortableFileQuery, FilterableFileQuery;

    /**
     * @OA\Get(
     *     path="/api/last-opened-files",
     *     summary="Get recently opened files and folders",
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
     *             @OA\Property(property="last_opened_folders", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="last_opened_files", type="array", @OA\Items(type="object"))
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
    public function lastOpenedFiles(Request $request)
    {
        $userId = Auth::id();
        $sortBy = $request->get('sort');

        try {
            $query = File::query()
                ->with('labels')
                ->join('file_access_logs as log', 'log.file_id', '=', 'files.id')
                ->where('log.user_id', $userId)
                ->whereNull('files.deleted_at')
                ->whereNotNull('files.parent_id')
                ->select('files.*', 'log.last_accessed_at');

            $query = $this->applyDmsFiltering($query, $request);

            // primary sort: last opened first
            $query->orderBy('log.last_accessed_at', 'desc');

            // secondary sort: user requested sort
            $secondarySortRule = $this->getSortRule($sortBy);
            if ($secondarySortRule) {
                $query->orderBy($secondarySortRule['column'], $secondarySortRule['direction']);
            }

            $query->orderBy('files.id', 'desc');

            $files = $query->limit(12)->get();

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
