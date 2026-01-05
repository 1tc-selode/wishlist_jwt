<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of users (admin only).
     */
    public function index()
    {
        $users = User::all();

        return response()->json($users);
    }

    /**
     * Display the specified user (admin only).
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        return response()->json($user);
    }

    /**
     * Update the specified user (admin only).
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8',
            'is_admin' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'email', 'is_admin']);

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Remove the specified user (admin only).
     * Ez a művelet SOFT DELETE-et hajt végre:
     * - A felhasználó NEM törlődik fizikailag az adatbázisból
     * - Csak a deleted_at mező kitöltődik az aktuális időponttal
     * - A felhasználó visszamarad az adatbázisban, de nem tud belépni
     * - A wishlist bejegyzések is automatikusan soft delete-elődnek (cascadeOnDelete)
     * - Lehetőség van később a visszaállításra (restore())
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Soft delete: deleted_at oszlop kitöltése
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully (soft delete)'
        ]);
    }
}
