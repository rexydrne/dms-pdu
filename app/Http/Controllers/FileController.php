<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\FilesActionRequest;
use App\Http\Requests\TrashFilesRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\File;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Resources\FileResource;
use App\Jobs\UploadFileToCloudJob;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Helpers\FileHelper;
use Exception;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    public function myFiles(Request $request, string $folderId = null)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'message' => 'Unauthenticated'],
                    401);
            }

            $search = $request->get('search');

            if ($folderId) {
                $folder = File::query()
                    ->where('created_by', Auth::id())
                    ->where('id', $folderId)
                    ->firstOrFail();
            } else {
                $folder = $this->getRoot();
            }

            $query = File::query()
                ->select('files.*')
                ->where('created_by', Auth::id())
                ->where('_lft', '!=', 1)
                ->orderBy('is_folder', 'desc')
                ->orderBy('files.created_at', 'desc')
                ->orderBy('files.id', 'desc');

            if ($search) {
                $query->where('name', 'like', "%$search%");
            } else {
                $query->where('parent_id', $folder->id);
            }

            $files = $query->paginate(10);
            $files = FileResource::collection($files);

            $ancestors = FileResource::collection([...$folder->ancestors, $folder]);
            $folder = new FileResource($folder);

            return response()->json([
                'files'     => $files,
                'folder'    => $folder,
                'ancestors' => $ancestors,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch files',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

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

        $files = $query->paginate(10);

        return FileResource::collection($files);
    }


    public function createFolder(StoreFolderRequest $request)
    {
        try {
            $data = $request->validated();
            $parent = $request->parent ?? $this->getRoot();
            $user   = $request->user();

            $uniqueName = FileHelper::generateUniqueName($data['name'], $parent->id, $user->id, true);

            // $exists = File::where('parent_id', $parent->id)
            //     ->where('name', $data['name'])
            //     ->where('is_folder', 1)
            //     ->whereNull('deleted_at')
            //     ->exists();

            // if ($exists) {
            //     throw ValidationException::withMessages([
            //         'name' => ["Folder \"{$data['name']}\" already exists in this directory."],
            //     ]);
            // }

            $file = new File();
            $file->is_folder = 1;
            // $file->name = $data['name'];
            $file->name = $uniqueName;
            $parent->appendNode($file);

            return response()->json([
                'message' => 'Folder created successfully',
                'folder'  => new FileResource($file)
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to create folder',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreFileRequest $request)
    {
        try {
            $data = $request->validated();
            $parent = $request->parent ?? $this->getRoot();
            $user   = $request->user();
            $fileTree = $request->file_tree;

            if (!empty($fileTree)) {
                $this->saveFileTree($fileTree, $parent, $user);
            } else {
                foreach ($data['files'] as $file) {
                    $this->saveFile($file, $user, $parent);
                }
            }

            return response()->json([
                'message' => 'Files uploaded successfully',
                'parent'  => new FileResource($parent),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to upload files',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function getRoot()
    {
        return File::query()
            ->whereIsRoot()
            ->where('created_by', Auth::id())
            ->firstOrFail();
    }

    public function saveFileTree($fileTree, $parent, $user)
    {
        foreach ($fileTree as $name => $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $this->saveFile($file, $user, $parent);
            } elseif (is_array($file)) {
                $uniqueName = FileHelper::generateUniqueName($name, $parent->id, $user->id, true);
                // $existing = File::where('parent_id', $parent->id)
                //     ->where('name', $name)
                //     ->where('is_folder', 1)
                //     ->whereNull('deleted_at')
                //     ->first();

                // if ($existing) {
                //     throw ValidationException::withMessages([
                //         'name' => ["Folder \"$name\" already exists in this directory."]
                //     ]);
                // }

                $folder = new File();
                $folder->is_folder = 1;
                $folder->name = $uniqueName;
                $parent->appendNode($folder);

                $this->saveFileTree($file, $folder, $user);
            }
        }
    }

    private function saveFile($file, $user, $parent): void
    {
        $name = $file->getClientOriginalName();
        $uniqueName = FileHelper::generateUniqueName($ame, $parent->id, $user->id);

        // $existing = File::where('parent_id', $parent->id)
        //     ->where('name', $name)
        //     ->where('is_folder', 0)
        //     ->whereNull('deleted_at')
        //     ->first();

        // if ($existing) {
        //     throw ValidationException::withMessages([
        //         'files' => ["File \"$name\" already exists in this directory."]
        //     ]);
        // }

        $path = $file->store('/files/' . $user->id, 'local');

        $model = new File();
        $model->storage_path = $path;
        $model->is_folder = false;
        $model->name = $uniqueName;
        $model->mime = $file->getMimeType();
        $model->size = $file->getSize();
        $model->uploaded_on_cloud = 0;

        $parent->appendNode($model);

        // UploadFileToCloudJob::dispatch($model);
    }

    public function destroy(FilesActionRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;

        if ($data['all']) {
            $children = $parent->children;
            foreach ($children as $child) {
                $child->moveToTrashWithDescendants();
            }
        } else {
            foreach ($data['ids'] ?? [] as $id) {
                $file = File::find($id);
                if ($file) {
                    $file->moveToTrashWithDescendants();
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'File(s)/folder(s) moved to trash successfully.',
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
                // restore parent chain
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

}
