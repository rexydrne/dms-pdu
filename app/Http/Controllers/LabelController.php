<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Dotenv\Exception\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LabelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $labels = Label::all();
            return response()->json([
                'message' => 'Labels retrieved successfully',
                'data' => $labels
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve all label due to a database error.',
                'error-message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve all label: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try{
            $request->validate([
                'name' => 'required|string|unique|max:255',
                'color' => 'required|string|max:255',
            ]);

            return DB::transaction (function () use ($request) {
                $user = Auth::user();
                $label = Label::create([
                    'name' => $request->name,
                    'color' => $request->color,
                    'created_by' => $user->id,
                ]);

                return response()->json([
                    'message' => 'Label created successfully',
                    'data' => $label
                ], 201);
            });

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create label due to a database error.',
                'error-message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create label: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $label = Label::findOrFail($id);
            return response()->json([
                'message' => 'Label retrieved successfully',
                'data' => $label
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve label due to a database error.',
                'error-message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve label: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'color' => 'sometimes|required|string|max:255',
            ]);

            return DB::transaction(function () use ($request, $id) {
                $label = Label::findOrFail($id);

                $label->fill($request->only(['name', 'color']));

                $label->save();

                return response()->json([
                    'message' => 'Label updated successfully',
                    'data' => $label
                ], 200);
            });
        } catch (ValidationException $e){
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update label due to a database error.',
                'error-message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update label: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $label = Label::findOrFail($id);

                if (!$label){
                    return response()->json([
                        'message' => 'Label not found'
                    ], 404);
                }

                $label->delete();

                return response()->json([
                    'message' => 'Label deleted successfully'
                ], 200);
            });
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete label due to a database error.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete label: ' . $e->getMessage(),
            ], 500);
        }
    }
}
