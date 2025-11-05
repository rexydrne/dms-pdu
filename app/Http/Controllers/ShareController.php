<?php

namespace App\Http\Controllers;

use App\Mail\FileSharedMail;
use App\Models\File;
use App\Models\Shareable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Pail\ValueObjects\Origin\Console;
use Ramsey\Uuid\Type\Integer;
use Spatie\Permission\Models\Permission;
use Str;

class ShareController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(File $fileId){

        try {
            if (!Auth::check()) {
                return response()->json([
                    'message' => 'Unauthenticated'],
                    401);
            }

            if (Auth::id() !== $fileId->created_by) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view shared users for this file.',
                ], 403);
            }

            $sharedfile = Shareable::where('file_id', $fileId->id)
                ->with(['user', 'permission'])
                ->get()
                ->map(function ($share) {
                    return [
                        'user' => $share->user->fullname,
                        'email' => $share->user->email,
                        'permission' => $share->permission->name,
                    ];
                });

            return response()->json([
                'success' => true,
                'file_id' => $fileId->id,
                'file_name' => $fileId->name,
                'file_owner' => [
                    'id' => $fileId->user->id,
                    'name' => $fileId->user->fullname,
                    'email' => $fileId->user->email,
                ],
                'data' => $sharedfile,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve shared users: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function userSharedFiles($fileId, $email){
        try {
            if (!Auth::check()) {
                return response()->json([
                    'message' => 'Unauthenticated'],
                    401);
            }

            if (Auth::id() !== File::find($fileId)->created_by) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view shared users for this file.',
                ], 403);
            }

            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => "User with email $email not found.",
                ], 404);
            }

            $userId = $user->id;

            $sharedFiles = Shareable::where('user_id', $userId)
            ->where('file_id', $fileId)
            ->with(['file', 'permission', 'file.user'])
            ->get()
            ->map(function ($share) {
                return [
                    'file_id' => $share->file->id,
                    'file_name' => $share->file->name,
                    'shared_by' => [
                        'id' => $share->file->user->id,
                        'name' => $share->file->user->fullname,
                        'email' => $share->file->user->email,
                    ],
                    'permission' => $share->permission->name,
                ];
            });


            return response()->json([
                'success' => true,
                'data' => $sharedFiles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve shared files: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request, $id)
    {
        try {
            Log::info('ShareController@store called by user ID: ' . Auth::id());

            $file = File::findOrFail($id);
            $sharedBy = Auth::user();

            Log::info('File id shared ' . $file->created_by);

            if ($sharedBy->id !== $file->created_by) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to share this file.',
                ], 403);
            }

            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $validated = $request->validate([
                'permission_id' => 'required|exists:permissions,id',
                'emails' => 'required|array|min:1',
                'emails.*' => 'email|exists:users,email',
            ]);

            foreach ($validated['emails'] as $email) {
                $user = User::where('email', $email)->first();

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => "User with email $email not found.",
                    ], 404);
                }

                $alreadyShared = Shareable::where('file_id', $file->id)
                    ->where('shared_to', $user->id)
                    ->exists();

                if ($alreadyShared) {
                    return response()->json([
                        'success' => false,
                        'message' => "File already shared to $email.",
                    ], 400);
                }
            }

            foreach ($validated['emails'] as $email) {
                $user = User::where('email', $email)->firstOrFail();

                $token = \Illuminate\Support\Str::uuid();
                $shareLink = url("api/share/{$token}");

                $file->shares()->attach(
                    $user->id,
                    [
                        'permission_id' => $validated['permission_id'],
                        'shared_by' => $sharedBy->id,
                        'token' => $token,
                    ]
                );

                Mail::to($user->email)->send(
                    new FileSharedMail($file, $sharedBy, $user, $shareLink)
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'File shared successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('ShareController@store failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to share file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function accessSharedFile($token)
    {
        $share = Shareable::where('token', $token)->first();

        if (!$share) {
            abort(404, 'This link is invalid.');
        }

        $file_path = File::where('id', $share->file_id)->first()->storage_path;

        return redirect()->to("http://127.0.0.1:8000/storage/{$file_path}");
        // return redirect()->to("http://pdu-dms.my.id/{$file_path}");
    }

    /**
     * Display the specified resource.
     */

    public function sharedWithMe(){
        try{
            if (!Auth::check()) {
                return response()->json([
                    'message' => 'Unauthenticated'],
                    401);
            }

            $user = Auth::user();
            $sharedFiles = $user->sharedFiles()
                ->with(['user'])
                ->get()
                ->map(function ($file) {
                    return [
                        'file_id' => $file->id,
                        'file_name' => $file->name,
                        'file_path' => $file->path,
                        'shared_by' => [
                            'id' => $file->user->id,
                            'name' => $file->user->fullname,
                            'email' => $file->user->email,
                        ],
                        'permission' => Permission::find($file->pivot->permission_id)->name,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $sharedFiles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve shared files: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try{
            $shareable = Shareable::findOrFail($id);

            $file = File::find($shareable->file_id);

            if (Auth::id() !== $file->created_by) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to update this share.',
                ], 403);
            }

            $request->validate([
                'permission_id' => 'required|exists:permissions,id',
            ]);

            return DB::transaction (function () use ($request, $shareable) {
                $shareable->update([
                    'permission_id' => $request->permission_id,
                ]);

                return response()->json([
                    'message' => 'Shareable updated successfully',
                    'data' => $shareable
                ], 200);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update share file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $shareable = Shareable::findOrFail($id);

                $file = File::find($shareable->file_id);

                if (Auth::id() !== $file->created_by) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have permission to delete this share.',
                    ], 403);
                }

                $shareable->delete();

                return response()->json([
                    'message' => 'Shareable deleted successfully',
                ], 200);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete share file: ' . $e->getMessage(),
            ], 500);
        }
    }
}
