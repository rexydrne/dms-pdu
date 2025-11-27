<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Http\Resources\FileResource;
use App\Helpers\FileHelper;
use App\Http\Requests\Trash\TrashFilesRequest;
use App\Http\Requests\Trash\ForceDeleteFilesRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class TrashController extends Controller
{
    public function trash(Request $request)
    {
        $search = $request->get('search');

        $query = File::onlyTrashed()
            ->where('created_by', Auth::id())
            ->orderBy('is_folder', 'desc')
            ->orderBy('deleted_at', 'desc')
            ->orderBy('files.id', 'desc');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $files = $query->get();

        $message = $files->isNotEmpty()
            ? 'Successfully retrieved files from trash.'
            : 'Trash is empty.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'files' => FileResource::collection($files)
        ]);
    }

    public function restore(TrashFilesRequest $request)
    {
        $data = $request->validated();

        if ($data['all']) {
            $children = File::onlyTrashed()
                ->with('parent')
                ->where('created_by', Auth::id())
                ->get();
        } else {
            $ids = $data['ids'] ?? [];

            $children = File::onlyTrashed()
                ->with('parent')
                ->whereIn('id', $ids)
                ->where('created_by', Auth::id())
                ->get();

            foreach ($children as $child) {
                $parent = $child->parent;
                while ($parent && $parent->trashed()) {
                    $parent->restore();
                    $parent = $parent->parent;
                }

                $child->name = FileHelper::generateUniqueName(
                    $child->name,
                    $child->parent_id,
                    Auth::id()
                );

                $child->restore();

                if ($child->is_folder) {
                    File::onlyTrashed()
                        ->whereDescendantOf($child)
                        ->update(['deleted_at' => null]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'File(s) restored successfully.',
        ]);
    }

    public function forceDestroy(ForceDeleteFilesRequest $request)
    {
        $data = $request->validated();

        if (!empty($data['all'])) {
            $files = File::onlyTrashed()
                ->leftJoin('shareables', 'shareables.file_id', 'files.id')
                ->where(function ($q) {
                    $q->where('files.created_by', Auth::id())
                        ->orWhere('shareables.user_id', Auth::id());
                })
                ->select('files.*')
                ->orderBy('_lft', 'desc')
                ->get();
        } else {
            $ids = $data['ids'] ?? [];
            $files = File::onlyTrashed()
                ->whereIn('id', $ids)
                ->orderBy('_lft', 'desc')
                ->get();
        }

        foreach ($files as $file) {
            try {
                if ($file->is_folder) {
                    $descendants = File::onlyTrashed()
                        ->whereDescendantOf($file)
                        ->orderBy('_lft', 'desc')
                        ->get();

                    foreach ($descendants as $desc) {
                        if (! $desc->is_folder && $desc->storage_path && Storage::disk('public')->exists($desc->storage_path)) {
                            Storage::disk('public')->delete($desc->storage_path);
                        }
                        $desc->forceDelete();
                    }
                }

                if (! $file->is_folder && $file->storage_path && Storage::disk('public')->exists($file->storage_path)) {
                    Storage::disk('public')->delete($file->storage_path);
                }

                $file->forceDelete();
            } catch (Exception $e) {
                Log::error("Failed to permanently delete file id {$file->id}: {$e->getMessage()}");
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'File(s) permanently deleted.',
        ]);
    }
}
