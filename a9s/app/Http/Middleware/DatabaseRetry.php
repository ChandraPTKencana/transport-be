<?php

namespace App\Http\Middleware;

use App\Exceptions\MyException;
use Closure;
use Illuminate\Http\Request;

class DatabaseRetry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next) {
        $maxRetries = 3;
        $retryCount = 0;
    
        do {
            try {
                return $next($request);
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'server has gone away')) {
                    $retryCount++;
                    if ($retryCount >= $maxRetries) {
                        throw new MyException(["message" => "Service unavailable"], 503);
                    }
                    usleep(500000); // Tunggu 500ms sebelum retry
                    continue;
                }
                throw $e;
            }
        } while ($retryCount < $maxRetries);
    }
}
