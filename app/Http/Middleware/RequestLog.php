<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RequestLog
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
        foreach(config('adminpanel.log_urls') as $url){
            if(env('APP_URL').'api/admin/'.$url == $request->url()){
                DB::table('request_log')->insert([
                    'data_id' => $request->id ?? null,
                    'endpoint' => $request->url(),
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'request_data' => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
                    'ip_address' => $request->ip(),
                    'user_id' => Auth::user()->id ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        return $next($request);
    }
}