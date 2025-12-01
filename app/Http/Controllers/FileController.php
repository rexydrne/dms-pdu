<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\DownloadFileRequest;
use App\Http\Requests\DeleteFileRequest;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFileRequest;
use App\Http\Requests\DuplicateFileRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\Shareable;
use App\Models\ShareLink;
use App\Models\FileAccessLog;
use App\Jobs\UploadFileToCloudJob;
use App\Helpers\FileHelper;
use Exception;
use Spatie\Permission\Models\Role;
use App\Traits\SortableFileQuery;
use App\Traits\FilterableFileQuery;
use Illuminate\Database\Eloquent\Builder;

class FileController extends Controller
{
    use SortableFileQuery, FilterableFileQuery;

    public function myFiles(Request $request, string $folderId = null)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'message' => 'Unauthenticated'],
                    401);
            }

            $search = $request->get('search');
            $sortBy = $request->get('sort');

            if ($folderId) {
                $userId = Auth::id();

                $folder = File::query()
                    ->where('id', $folderId)
                    ->where(function (Builder $query) use ($userId) {
                        $query->where('created_by', $userId)
                            ->orWhereHas('shareables', function (Builder $q) use ($userId) {
                                $q->where('shared_to', $userId);
                            });
                    })
                    ->firstOrFail();

                $this->trackAccess((int)$folderId, Auth::id());
            } else {
                $folder = $this->getRoot();
            }

            $query = File::query()
                ->select('files.*')
                ->where('_lft', '!=', 1)
                ->with('labels');

            if ($search) {
                $query->where('name', 'like', "%$search%");
            } else {
                $query->where('parent_id', $folder->id);
            }

            $query = $this->applyDmsFiltering($query, $request);
            $query = $this->applyDmsSorting($query, $sortBy);

            $files = $query->get();
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

    private function trackAccess(int $fileId, int $userId): void
    {
        $file = File::find($fileId);

        if(! $file || $file->isRoot()) {
            return;
        }

        FileAccessLog::updateOrCreate(
            ['user_id' => $userId, 'file_id' => $fileId],
            ['last_accessed_at' => now()]
        );
    }

    public function createFolder(StoreFolderRequest $request)
    {
        try {
            $data = $request->validated();
            $parent = $request->parent ?? $this->getRoot();
            $user   = $request->user();

            $uniqueName = FileHelper::generateUniqueName($data['name'], $parent->id, $user->id, true);

            $file = new File();
            $file->is_folder = 1;
            $file->name = $uniqueName;
            $file->path = Str::slug($uniqueName);
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

            $uploadedFiles = [];

            if (!empty($fileTree)) {
                $uploadedFiles = $this->saveFileTree($fileTree, $parent, $user);
            } else {
                foreach ($data['files'] as $file) {
                    $uploadedFile = $this->saveFile($file, $user, $parent);
                    $uploadedFiles[] = $uploadedFile;
                }
            }

            $uploadedFileIds = collect($uploadedFiles)->pluck('id')->toArray();
            $uploadedFilesWithLabels = File::whereIn('id', $uploadedFileIds)->with('labels')->get();

            return response()->json([
                'message' => 'Files uploaded successfully',
                'files'  => FileResource::collection($uploadedFilesWithLabels),
                'parent' => new FileResource($parent),
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
        $uploadedFiles = [];

        foreach ($fileTree as $name => $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $uploadedFile = $this->saveFile($file, $user, $parent);
                $uploadedFiles[] = $uploadedFile;
            } elseif (is_array($file)) {
                $uniqueName = FileHelper::generateUniqueName($name, $parent->id, $user->id, true);

                $folder = new File();
                $folder->is_folder = 1;
                $folder->name = $uniqueName;
                $folder->path = Str::slug($uniqueName);
                $parent->appendNode($folder);

                $uploadedFiles[] = $folder;

                $childFiles = $this->saveFileTree($file, $folder, $user);
                $uploadedFiles = array_merge($uploadedFiles, $childFiles);
            }
        }
        return $uploadedFiles;
    }

    private function saveFile($file, $user, $parent): File
    {
        $name = $file->getClientOriginalName();
        $uniqueName = FileHelper::generateUniqueName($name, $parent->id, $user->id);

        $path = $file->store('/files/' . $user->id, 'public');
        $slugCandidate = str_replace('.', ' ', $uniqueName);
        $slugCandidate = str_replace(['(', ')'], ' ', $slugCandidate);
        $newPath = Str::slug($slugCandidate);

        $model = new File();
        $model->storage_path = $path;
        $model->is_folder = false;
        $model->name = $uniqueName;
        $model->path = $newPath;
        $model->mime = $file->getMimeType();
        $model->size = $file->getSize();
        $model->uploaded_on_cloud = 0;

        $parent->appendNode($model);

        if (request()->has('labels')) {
            $labels = request()->input('labels');
            $model->labels()->sync($labels);
        }

        return $model;
    }

    public function update(UpdateFileRequest $request, string $fileId)
    {
        try {
            $file = $request->file;

            if (!$file->isOwnedBy(Auth::id())) {
                return response()->json(['message' => 'Unauthorized to modify this file/folder'], 403);
            }

            $oldName = $file->name;
            $newName = $request->validated('name');

            $file->name = $newName;

            if (!$file->is_folder) {
                $slugCandidate = str_replace('.', ' ', $newName);
                $newPath = Str::slug($slugCandidate);

                $file->path = $newPath;

                $labelIds = $request->validated('label_ids');
                if (is_array($labelIds)) {
                    $file->labels()->sync($labelIds);
                }
            } else {
                 $file->path = Str::slug($newName);
            }

            $file->save();

            $this->trackAccess((int)$file->id, Auth::id());

            $updatedFile = File::query()->where('id', $file->id)->with('labels')->first();

            return response()->json([
                'message' => $file->is_folder
                    ? "Folder '$oldName' successfully renamed to '$newName'"
                    : "File '$oldName' successfully updated to '$newName'",
                'file' => new FileResource($updatedFile),
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'File not found or access denied'], 404);
        } catch (Exception $e) {
            Log::error("Failed to update file: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update file/folder',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(DeleteFileRequest $request, string $fileId)
    {
        $data = $request->validated();

        $file = File::find($data['file_id']);
        if ($file) {
            $file->moveToTrashWithDescendants();
        }

        return response()->json([
            'success' => true,
            'message' => 'File(s)/folder(s) moved to trash successfully.',
        ]);
    }

    public function viewFile(Request $request, $fileId)
    {
        $fileRecord = File::findOrFail($fileId);
        $user = $request->user();

        $shareables = Shareable::where('file_id', $fileId)
            ->where('shared_to', $user->id)
            ->first();

        $role = Role::where('name', 'receiver')->first();

        Log::info("Shareable record", [
            'role_id_on_record' => $shareables ? $shareables->role_id : null,
            'expected_role_id' => $role ? $role->id : null,
        ]);

        $isCreator = $user->id === $fileRecord->created_by;
        $isSharedReceiver = $shareables && (int)$shareables->role_id === (int)$role->id;

        if (!($isCreator || $isSharedReceiver)) {
            abort(403, 'Access Denied. You do not have permission to view this file.');
        }

        $this->trackAccess((int)$fileId, $user->id);

        $path = $fileRecord->storage_path;

        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'File is not found in server.');
        }

        Log::info("Serving file from storage", ['path' => $path]);

        return Storage::disk('public')->response($path);
    }

    public function download(DownloadFileRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;
        $parentName = $parent->name ?? null;

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download'
            ];
        }

        if ($all) {
            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Parent folder is required when "all" is true.']
                ]);
            }

            $zipPath = $this->createZip($parent->children);
            $url = url('/api/storage-file') . '?path=' . urlencode($zipPath);
            $filename = $parent->name . '.zip';
        } else {
            [$url, $filename] = $this->getDownloadUrl($ids, $parentName);
        }

        return [
            'url' => $url,
            'filename' => $filename
        ];
    }

    public function createZip($files): string
    {
        $zipPath = 'zip/' . Str::random() . '.zip';
        $publicPath = "$zipPath";

        if (!Storage::disk('public')->exists(dirname($publicPath))) {
            Storage::disk('public')->makeDirectory(dirname($publicPath));
        }

        $zipFile = Storage::disk('public')->path($publicPath);

        $zip = new \ZipArchive();

        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $this->addFilesToZip($zip, $files);
            $zip->close();

            if (! Storage::disk('public')->exists($zipPath)) {
                Log::error("Zip file was not created at expected path: {$zipFile}");
                throw ValidationException::withMessages([
                    'zip' => ['Failed to create zip archive.']
                ]);
            }
            return $zipPath;
        }

        Log::error("Failed to open zip archive for creation: {$zipFile}");
        throw ValidationException::withMessages([
            'zip' => ['Failed to create zip archive.']
        ]);


        return Storage::disk('public')->url($zipPath);
    }

    private function addFilesToZip($zip, $files, $ancestors = '')
    {
        if (! Storage::disk('public')->exists('tmp')) {
            Storage::disk('public')->makeDirectory('tmp');
        }

        foreach ($files as $file) {
            if ($file->is_folder) {
                $children = $file->children ?? $file->load('children')->children;
                $this->addFilesToZip($zip, $file->children, $ancestors . $file->name . '/');
                continue;
            }

            try {
                if (! $file->storage_path) {
                    continue;
                }

                if (Storage::disk('public')->exists($file->storage_path)) {
                    $localPath = Storage::disk('public')->path($file->storage_path);
                } else {
                    if (! Storage::exists($file->storage_path)) {
                        Log::warning("Source file not found on any disk: {$file->storage_path} (id: {$file->id})");
                        continue;
                    }

                    $content = Storage::get($file->storage_path);
                    $dest = 'tmp/' . Str::random(8) . '_' . basename($file->storage_path);
                    Storage::disk('public')->put($dest, $content);
                    $localPath = Storage::disk('public')->path($dest);
                }

                if (file_exists($localPath)) {
                    $zip->addFile($localPath, $ancestors . $file->name);
                } else {
                    Log::warning("Local path for zipping does not exist: {$localPath} (file id: {$file->id})");
                }
            } catch (Exception $e) {
                Log::warning("Failed to add file to zip: {$file->id} - {$e->getMessage()}");
                continue;
            }

        }
    }

    private function getDownloadUrl(array $ids, $zipName)
    {
        if (count($ids) === 1) {
            $file = File::withTrashed()->findOrFail($ids[0]);

            if ($file->is_folder) {
                $children = $file->children()->get();
                if ($children->isEmpty()) {
                    throw ValidationException::withMessages([
                        'ids' => ['The folder is empty.']
                    ]);
                }

                $zipPath = $this->createZip($children);
                $url = url('/api/storage-file') . '?path=' . urlencode($zipPath);
                $filename = $file->name . '.zip';

                return [$url, $filename];
            }

            if ($file->storage_path) {
                return [url('/api/view-file/' . $file->id), $file->name];
            }

            if (Storage::exists($file->storage_path)) {
                return [Storage::url($file->storage_path), $file->name];
            }

            throw ValidationException::withMessages([
                'ids' => ['Storage path missing for the file.']
            ]);
        }

        $files = File::withTrashed()->whereIn('id', $ids)->get();
        if ($files->isEmpty()) {
            throw ValidationException::withMessages([
                'ids' => ['No files found to download.']
            ]);
        }

        $zipPath = $this->createZip($files);
        $url = url('/api/storage-file') . '?path=' . urlencode($zipPath);
        $filename = ($zipName ?: 'files') . '.zip';

        return [$url, $filename];
    }

    public function serveStorageFile(Request $request)
    {
        $path = $request->query('path');

        if (! $path) {
            throw ValidationException::withMessages(['path' => 'Path is required']);
        }

        if (! preg_match('/^(zip|tmp)\//', $path)) {
            abort(403, 'Forbidden');
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->download($path);
    }

    public function getFileInfo(Request $request, string $fileId)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $file = File::query()
                ->where('id', $fileId)
                ->where(function ($query) {
                    $query->where('created_by', Auth::id())
                          ->orWhereHas('shareables', function ($q) {
                              $q->where('shared_to', Auth::id());
                          });
                })
                ->with(['labels', 'shareables.user', 'shareables.role', 'user'])
                ->firstOrFail();

            $this->trackAccess((int)$fileId, Auth::id());
            $ancestors = $file->ancestors()->get();

            $shares = $file->shareables->map(function ($share) use ($file) {
                return [
                    'user_id' => $share->user->id,
                    'user_name' => $share->user->fullname,
                    'user_email' => $share->user->email,
                    'photo_profile_path' => $share->user->photo_profile_path,
                    'role' => $share->role->name,
                ];
            });

            $locationPath = 'MySpace';
            foreach ($ancestors as $ancestor) {
                if (!$ancestor->isRoot()) {
                    $locationPath .= '/' . $ancestor->name;
                }
            }

            $locationPath .= '/' . $file->name;

            $fileResource = new FileResource($file);

            return response()->json([
                'file_info' => [
                    ...$fileResource->toArray($request),

                    'location' => $locationPath,
                    'shares' => $shares,
                    'owner_name' => $file->user->fullname,
                    "advanced_share" => url('/api/shared-file/'.$file->id),

                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'File not found or access denied'], 404);
        } catch (Exception $e) {
            Log::error("Failed to get file info: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch file information',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function duplicate(DuplicateFileRequest $request)
    {
        try {
            $data = $request->validated();
            $originalFile = File::with('labels')->findOrFail($data['file_id']);

            if (!$originalFile->isOwnedBy(Auth::id())) {
                return response()->json([
                    'message' => 'You can only duplicate files you own'
                ], 403);
            }

            $parent = $originalFile->parent ?? $this->getRoot();
            $user = Auth::user();

            if ($originalFile->is_folder) {
                $duplicatedFolder = $this->duplicateFolder($originalFile, $parent, $user);

                return response()->json([
                    'message' => 'Folder duplicated successfully',
                    'file' => new FileResource($duplicatedFolder)
                ], 201);
            } else {
                $duplicatedFile = $this->duplicateFile($originalFile, $parent, $user);

                return response()->json([
                    'message' => 'File duplicated successfully',
                    'file' => new FileResource($duplicatedFile)
                ], 201);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'File not found'], 404);
        } catch (Exception $e) {
            Log::error("Failed to duplicate file: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to duplicate file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function duplicateFile($originalFile, $parent, $user)
    {
        // Generate unique name with "Copy" suffix
        $baseName = $originalFile->name;
        $uniqueName = FileHelper::generateUniqueName(
            $this->getCopyName($baseName),
            $parent->id,
            $user->id,
            false
        );

        // Copy the physical file
        $newStoragePath = null;
        if ($originalFile->storage_path && Storage::disk('public')->exists($originalFile->storage_path)) {
            $extension = pathinfo($originalFile->storage_path, PATHINFO_EXTENSION);
            $newStoragePath = '/files/' . $user->id . '/' . Str::random(40) . '.' . $extension;

            Storage::disk('public')->copy(
                $originalFile->storage_path,
                $newStoragePath
            );
        }

        // Create new file record
        $slugCandidate = str_replace('.', ' ', $uniqueName);
        $slugCandidate = str_replace(['(', ')'], ' ', $slugCandidate);
        $newPath = Str::slug($slugCandidate);

        $duplicatedFile = new File();
        $duplicatedFile->storage_path = $newStoragePath;
        $duplicatedFile->is_folder = false;
        $duplicatedFile->name = $uniqueName;
        $duplicatedFile->path = $newPath;
        $duplicatedFile->mime = $originalFile->mime;
        $duplicatedFile->size = $originalFile->size;
        $duplicatedFile->uploaded_on_cloud = 0;

        $parent->appendNode($duplicatedFile);

        // Copy labels
        if ($originalFile->labels->isNotEmpty()) {
            $duplicatedFile->labels()->sync($originalFile->labels->pluck('id'));
        }

        return $duplicatedFile->load('labels');
    }

    private function duplicateFolder($originalFolder, $parent, $user)
    {
        // Generate unique folder name
        $uniqueName = FileHelper::generateUniqueName(
            $this->getCopyName($originalFolder->name),
            $parent->id,
            $user->id,
            true
        );

        // Create new folder
        $duplicatedFolder = new File();
        $duplicatedFolder->is_folder = true;
        $duplicatedFolder->name = $uniqueName;
        $duplicatedFolder->path = Str::slug($uniqueName);

        $parent->appendNode($duplicatedFolder);

        // Copy labels
        if ($originalFolder->labels->isNotEmpty()) {
            $duplicatedFolder->labels()->sync($originalFolder->labels->pluck('id'));
        }

        // Recursively duplicate children
        $children = $originalFolder->children()->get();
        foreach ($children as $child) {
            if ($child->is_folder) {
                $this->duplicateFolder($child, $duplicatedFolder, $user);
            } else {
                $this->duplicateFile($child, $duplicatedFolder, $user);
            }
        }

        return $duplicatedFolder->fresh('labels');
    }

    private function getCopyName($originalName)
    {
        // If name already contains "Copy", increment the number
        if (preg_match('/^(.*?)\s*\(Copy(\s+(\d+))?\)(\.[^.]+)?$/', $originalName, $matches)) {
            $baseName = $matches[1];
            $extension = $matches[4] ?? '';
            $copyNumber = isset($matches[3]) ? (int)$matches[3] + 1 : 2;
            return $baseName . " (Copy {$copyNumber})" . $extension;
        }

        // First copy
        $pathInfo = pathinfo($originalName);
        $nameWithoutExt = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        return $nameWithoutExt . " (Copy)" . $extension;
    }

}
