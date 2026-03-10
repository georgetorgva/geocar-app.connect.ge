<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Languages\Locales;
use App;

class LocalesMid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        if (session()->has('locale')) {
            Session::put('locale', session('locale'));
            app()->setLocale(session('locale'));
            return $next($request);
        } else {
//            $locale = App::make(LanguagesService::class)->getDefault();
            $currenct = config('app.locale');
//            if ($locale) {
//                $currenct = $locale->locale;
//            }
            Session::put('locale', $currenct);
            app()->setLocale($currenct);
            return $next($request);
        }
    }
}
