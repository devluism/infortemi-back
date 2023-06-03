<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyFutswapAccessKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Obtenemos el api-key que el usuario envia
        $key = $request->headers->get('x-api-key');
        // Si coincide con el valor almacenado en la aplicacion
        // la aplicacion se sigue ejecutando
        if (isset($key) && $key == config('futswap.apiKeyConfirmation')) {
            return $next($request);
        } else {
            // Si falla devolvemos el mensaje de error
            return response()->json(['error' => 'Unauthorized' ], 401);
        }
    }
}
