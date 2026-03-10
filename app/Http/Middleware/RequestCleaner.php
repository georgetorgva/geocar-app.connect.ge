<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Tymon\JWTAuth\Facades\JWTAuth;
use Closure;
use Exception;

class RequestCleaner
{

    public function handle($request, Closure $next)
    {

//        p($request->all());

        $data = $request->all();


        $data = $this->recursiveCleaner($data);
//        p($data);


        $request->replace($data);
//        p($request->all());

        return $next($request);


    }

    public function recursiveCleaner($data = []){
        foreach ($data as $k=>$v){
            if(is_array($v)){
                $data[$k] = $this->recursiveCleaner($v);
            }else{
                $data[$k] = $this->cleanString($v);
            }
        }

        return $data;
    }


    public function cleanString($data = ''){
        $data = strip_tags($data, "<br /><br><h1><h2><h3><radialGradient><h4><blockquote><h5><h6><i><ul><cite><ol><li><span><a><div><p><img><s><em><strong><table><tr><td><th><thead><tbody><svg><g><path><circle><linearGradient><defs><stop><rect><col><colgroup><u>");

        //removing inline js events
        $data = preg_replace("/([ ]on[a-zA-Z0-9_-]{1,}=)|([ ]on[a-zA-Z0-9_-]{1,}=\".*\")|([ ]on[a-zA-Z0-9_-]{1,}='.*')|([ ]on[a-zA-Z0-9_-]{1,}=.*[.].*)/","",$data);

        //removing inline js
        $data = preg_replace("/([ ]href.*=\".*javascript:.*\")|([ ]href.*='.*javascript:.*')|([ ]href.*=.*javascript:.*)|([ ]javascript:.*)/i","",$data);

        return $data;
    }


}
