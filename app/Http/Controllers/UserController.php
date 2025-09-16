<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function register(Request $request){
        try {
            $messages = [
                'email.required' => 'The email field is required',
                'email.email' => 'The email must be a valid email address',
                'email.exists' => 'The selected email is invalid',
                'fullname.required' => 'The fullname field is required',
                'username.reqiured' => 'The username fiels is required',
                'username.unique' => 'This username is already exist',
                'password.required' => 'The password field is required',
                'password.confirmed' => 'Password confirmation does not match'
            ];

            $validator = Validator::make(
                $request->all(),
                [
                    'fullname' => 'required|string',
                    'username' => 'required|string|unique:users,username',
                    'email' => 'required|email|unique:users,email',
                    'password' => 'required|confirmed'
                ],
                $messages
            );

            if ($validator->fails()){
                return response()->json([
                    'success' => false,
                    'message' => $validator->messages()->first()
                ], 422);
            }

            $data = $request->except('password_confirmation');
            $data['password'] = Hash::make($request->password);
            User::create($data);

            return response()->json([
                'message' => 'User Created',
                'status' => 'Success'
            ], 201);
        }

        catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Register',
                'error' => $e->getMessage()
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

            $validator = Validator::make(
                $request->all(),
                [
                    'email' => 'required|email',
                    'password' => 'required'
                ],
                $messages
            );

            if ($validator->fails()){
                return response()->json([
                    'success' => false,
                    'message' => $validator->messages()->first()
                ], 422);
            }

            $user = User::where('email', $request['email'])->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email not found.'
                ], 401);
            }

            if (!Hash::check($request['password'], $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Incorrect password, please insert the correct password.'
                ], 401);
            }

            $token = $user->createToken($user->name . '-AuthToken')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'access_token' => $token
            ], 200);
        }

        catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        }

        catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi error saat login',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
