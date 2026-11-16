<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $token = $request->bearerToken();
        $user = $token ? \DB::table('api_tokens')->join('pengguna','pengguna.id_pengguna','=','api_tokens.id_pengguna')->where('token',hash('sha256',$token))->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at','>',now()))->select('pengguna.*')->first() : null;
        abort_unless($user, 401, 'Token tidak valid.');
        abort_unless(in_array($user->role, $roles, true), 403, 'Akses ditolak.');
        $request->attributes->set('pengguna', $user);
        return $next($request);
    }
}
