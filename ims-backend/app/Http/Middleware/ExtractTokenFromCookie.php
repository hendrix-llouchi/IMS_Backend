<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ExtractTokenFromCookie
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->hasCookie('token') && !$request->headers->has('Authorization')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->cookie('token'));
        }

        return $next($request);
    }
}
