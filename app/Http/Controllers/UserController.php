<?php

namespace App\Http\Controllers;

use App\Mail\TokenMail;
use App\Models\User;
use App\Models\File;
use App\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::all();

            return response()->json([
                'success' => true,
                'data' => $users,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function register(Request $request)
    {
        try {
            $messages = [
                'email.required' => 'The email field is required.',
                'email.email' => 'The email must be a valid email address.',
                'email.unique' => 'This email is already registered.',
                'fullname.required' => 'The fullname field is required.',
                'password.required' => 'The password field is required.',
                'password.confirmed' => 'Password confirmation does not match.',
                'password.min' => 'The password must be at least 8 characters.',
            ];

            $validator = Validator::make($request->all(), [
                'fullname' => 'required|string|unique:users,fullname|max:255',
                'email' => 'required|email|unique:users,email|max:255',
                'password' => 'required|confirmed|min:8',
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            return DB::transaction(function () use ($request) {
                $user = User::create([
                    'fullname' => $request->fullname,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ]);

                $user->sendEmailVerificationNotification();

                try {
                    $user->assignRole('admin', 'api');
                } catch (RoleDoesNotExist $e) {
                    throw new \Exception('Role "admin" does not exist.' . $e->getMessage());
                }

                $root = new File();
                $root->name = $user->email;
                $root->is_folder = true;
                $root->created_by = $user->id;
                $root->updated_by = $user->id;
                $root->makeRoot()->save();

                return response()->json([
                    'success' => true,
                    'message' => 'User created successfully.',
                ], 201);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register due to a database error.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        try {
            $user = User::findOrFail($id);

            if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid verification link.'
                ], 400);
            }

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Email already verified.'
                ], 200);
            }

            $user->markEmailAsVerified();

            return response()->json([
                'status' => 'success',
                'message' => 'Email verified successfully.'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify email: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resendVerificationEmail(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Email already verified.'
                ], 200);
            }

            $user->sendEmailVerificationNotification();

            return response()->json([
                'status' => 'success',
                'message' => 'Verification email resent.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to resend verification email: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request){
        try {
            $messages = [
                'email.required' => 'The email field is required',
                'email.email' => 'The email must be a valid email address',
                'email.exists' => 'The selected email is invalid',
            ];

            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (Auth::attempt($credentials)) {
                $request->session()->regenerate();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Login successful',
                    'user' => Auth::user(),
                ], 200);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials. Please check your email or password.'
            ], 401);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi error saat login',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateUserProfile(Request $request){
        try {
            $request->validate([
                'fullname' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255|unique:users,email,' . $request->user()->id,
                'photo_profile' => 'nullable|image|max:2048',
            ]);


            return DB::transaction(function () use ($request) {
                $user = $request->user();

                if ($request->has('fullname')) {
                    $user->fullname = $request->input('fullname');
                }

                if ($request->has('email')) {
                    $user->email = $request->input('email');
                }

                if ($request->hasFile('photo_profile')) {
                    if (!empty($user->photo_profile_path)) {
                        Storage::disk('public')->delete('profile_photos/' . $user->photo_profile_path);
                    }

                    $extension = $request->file('photo_profile')->getClientOriginalExtension();
                    $basename = uniqid() . time();
                    $path = "{$basename}.{$extension}";
                    $request->file('photo_profile')->storeAs('profile_photos', $path, 'public');

                    $user->photo_profile_path = $path;
                }

                $user->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'data' => $user,
                ], 200);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing the request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deletePhotoProfile(Request $request){
        try {
            $user = $request->user();

            if (empty($user->photo_profile_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No profile photo to delete',
                ], 400);
            }

            Storage::disk('public')->delete('profile_photos/' . $user->photo_profile_path);

            $user->photo_profile_path = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile photo deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the profile photo: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function sendResetToken(Request $request)
    {
        try {
            $messages = [
                'email.required' => 'The email field is required',
                'email.email' => 'The email must be a valid email address',
                'email.exists' => 'The selected email is invalid',
            ];

            $validator = Validator::make(
                $request->all(),
                [
                    'email' => 'required|email|exists:users,email',
                ],
                $messages
            );

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->messages()->first(),
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            $token = rand(1000, 9999);
            $expiry = now()->addMinutes(2);

            $existing = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('expires_at', '>', now())
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token has already been sent. Please check your email.',
                ], 429);
            }

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'user_id' => $user->id,
                    'token' => $token,
                    'expires_at' => $expiry,
                    'used' => false,
                    'created_at' => now(),
                ]
            );

            Mail::send('mails.forgot-password', [
                'token' => $token,
                'expiry' => $expiry->toDateTimeString(),
            ], function ($message) use ($user) {
                $message->to($user->email);
                $message->subject('Token for Password Reset');
            });

            return response()->json([
                'message' => 'Token sent to your email',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in sendResetToken: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing the request',
            ], 500);
        }
    }

    public function verifyToken(Request $request){
        $messages = [
            'email.required' => 'The email field is required',
            'email.email' => 'The email must be a valid email address',
            'email.exists' => 'The selected email is invalid',
            'token.required' => 'The OTP field is required',
            'token.min' => 'The OTP must be at least 5 characters',
        ];

        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|email|exists:users,email',
                'token' => 'required|min:4',
            ],
            $messages
        );
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->messages()->first()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        $token = Token::query()->where('email', $request->email)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email not found.'
            ], 404);
        }

        if ($token->token != $request->token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid token.'
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Token is valid. Token verified successfully.'
        ], 200);
    }

    public function resetPassword(Request $request){
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'token' => 'required|min:4',
                'password' => 'required|confirmed|min:8',

            ]);
            $updatePassword = DB::table('password_reset_tokens')
                ->where([
                    'email' => $request->email,
                    'token' => $request->token,
                ])
                ->first();

            if (!$updatePassword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP',
                ], 422);
            }
            $user = User::query()->where('email', $request->email)
                ->update(['password' => Hash::make($request->password)]);

            DB::table('password_reset_tokens')->where(['email' => $request->email])->delete();

            return response()->json([
                'success' => true,
                'message' => 'Successfully reset password',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function changePassword(Request $request){
        try {
            $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|confirmed|min:8',
            ]);

            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 422);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully. Please log in again with your new password.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the password: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request){
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged out'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to logout',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
