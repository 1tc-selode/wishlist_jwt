<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    /**
     * Display a listing of the user's wishlists.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $wishlists = Wishlist::where('user_id', $user->id)
            ->with('product')
            ->get();

        return response()->json($wishlists);
    }

    /**
     * Display all wishlists (admin only).
     */
    public function indexAll()
    {
        $wishlists = Wishlist::with(['user', 'product'])->get();

        return response()->json($wishlists);
    }

    /**
     * Add a product to the user's wishlist.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check if already in wishlist
        $exists = Wishlist::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Product already in wishlist'
            ], 409);
        }

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'added_at' => now(),
        ]);

        $wishlist->load('product');

        return response()->json([
            'message' => 'Product added to wishlist',
            'wishlist' => $wishlist
        ], 201);
    }

    /**
     * Display the specified wishlist item.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $wishlist = Wishlist::with('product')->find($id);

        if (!$wishlist) {
            return response()->json([
                'message' => 'Wishlist item not found'
            ], 404);
        }

        // Check if user owns this wishlist item or is admin
        if ($wishlist->user_id !== $user->id && !$user->is_admin) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json($wishlist);
    }

    /**
     * Remove a product from the user's wishlist.
     * Ez a művelet SOFT DELETE-et hajt végre:
     * - A wishlist bejegyzés NEM törlődik fizikailag
     * - Csak a deleted_at mező kitöltődik
     * - Ha ugyanazt a terméket újra hozzáadja, működni fog (unique constraint ki van szűrve)
     * - Lehetőség van később a visszaállításra (restore())
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $wishlist = Wishlist::find($id);

        if (!$wishlist) {
            return response()->json([
                'message' => 'Wishlist item not found'
            ], 404);
        }

        // Check if user owns this wishlist item or is admin
        if ($wishlist->user_id !== $user->id && !$user->is_admin) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Soft delete: deleted_at oszlop kitöltése
        $wishlist->delete();

        return response()->json([
            'message' => 'Product removed from wishlist (soft delete)'
        ]);
    }

    /**
     * Get user's wishlist by user ID (admin only).
     */
    public function getUserWishlist($userId)
    {
        $wishlists = Wishlist::where('user_id', $userId)
            ->with('product')
            ->get();

        return response()->json($wishlists);
    }
}
