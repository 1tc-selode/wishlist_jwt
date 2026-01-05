<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = auth()->user();
            
            if (!$user || !$user->is_admin) {
                return response()->json([
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Token is invalid or expired'
            ], 401);
        }

        return $next($request);
    }
}
