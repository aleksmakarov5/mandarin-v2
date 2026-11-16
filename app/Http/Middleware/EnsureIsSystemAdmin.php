<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsSystemAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isSystemAdmin()) {
            return response()->json([
                'message' => 'Доступ запрещен. Требуются права системного администратора.'
            ], 403);
        }

        return $next($request);
    }
}