<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Tymon\JWTAuth\Facades\JWTAuth;
use Closure;
use Exception;

class ResponseCacheMiddleware
{

    public function handle($request, Closure $next)
    {

//        p($request->all());

        $data = $request->all();


//        $data = $this->recursiveCleaner($data);
//        p($data);


//        $request->replace($data);
//        p($request->all());
//return $next;
        return $next($request);


    }


}
