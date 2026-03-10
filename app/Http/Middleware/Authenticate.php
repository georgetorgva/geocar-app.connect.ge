<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Closure;
use Exception;

class Authenticate extends Middleware
{

    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }


     public function handle($request, Closure $next, ...$guards)
     {
//         print 11111;
         $routePath = $request->segment(2);
//         $routePath = $request->segment(3);
//         print "{$routePath}";
//         p($request->segments());
         $neccesaryToAuth = ['member', 'admin'];

         if(Auth::user()){

             if ($routePath == 'admin' && Auth::user()->status != 'admin' ){
                 Auth::logout();
//                 throw new Exception('user is not admin');
                 return response(['unauthorized 1'], 201);
             }else if ($routePath !== 'admin' && Auth::user()->status == 'admin'){
                 Auth::logout();
//                 throw new Exception('admin restricted on site');
                 return response(['unauthorized 2'], 201);
             }

             return $next($request);
         }else{

             if ( array_search($routePath, $neccesaryToAuth) === true ){
//                 Auth::logout();
                 return response(['unauthorized 3'], 201);
             }

         }

         return $next($request);


     }

}
