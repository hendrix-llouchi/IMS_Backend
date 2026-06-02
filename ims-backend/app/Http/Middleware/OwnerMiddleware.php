<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class OwnerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user || $user->role !== 'owner') {
            return response()->json(['message' => 'Forbidden. Owner access only.'], 403);
        }

        return $next($request);
    }
}