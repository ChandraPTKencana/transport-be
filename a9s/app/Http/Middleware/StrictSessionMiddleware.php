<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class StrictSessionMiddleware
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
        // Hanya terima cookie session yang sesuai config
        $validSessionName = config('session.cookie');
        
        if ($request->cookies->has($validSessionName)) {
            // Jika ada cookie yang valid, abaikan cookie session lain
            foreach ($request->cookies->all() as $name => $value) {
                if (preg_match('/_session$/', $name) && $name !== $validSessionName) {
                    $request->cookies->remove($name);
                }
            }
        }
        
        return $next($request);
    }
}
