<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization', '');
        $token = preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)
            ? trim($matches[1])
            : '';

        if ($token === '') {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }

        $record = DB::table('api_tokens')
            ->where('token', hash('sha256', $token))
            ->first();

        if (! $record) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }

        $user = json_decode($record->user);
        if (! is_object($user) || ! isset($user->id, $user->name, $user->email, $user->role)) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }

        $request->attributes->set('authUser', $user);

        return $next($request);
    }
}
