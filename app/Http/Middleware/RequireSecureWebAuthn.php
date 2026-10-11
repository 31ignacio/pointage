<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSecureWebAuthn
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        abort_unless($request->isSecure() || $isLocalhost, 400,
            'Les empreintes nécessitent HTTPS. En développement, utilisez localhost.');

        return $next($request);
    }
}
