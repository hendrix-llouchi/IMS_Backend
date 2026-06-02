<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class WorkerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user || $user->role !== 'worker') {
            return response()->json(['message' => 'Forbidden. Worker access only.'], 403);
        }

        return $next($request);
    }
}