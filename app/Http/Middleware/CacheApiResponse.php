<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheApiResponse
{
    /**
     * Methods that write data or return user-specific content — never cache.
     */
    protected array $skipMethods = [
        'saveSubmitedForm',
        'adwrd',
        'uploadfile',
        'updateXratesApi',
        'getValidCookiesData',
        'anytest',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $method = $request->route('method') ?? last($request->segments());

        if (!$method || in_array($method, $this->skipMethods)) {
            return $next($request);
        }

        // Include lang in cache key so each locale gets its own cached entry
        $lang = $request->header('lang', $request->input('lang', 'default'));
        $cacheKey = 'parts_' . $method . '_' . md5(json_encode($request->all()) . $lang);

        $cached = Cache::store('file')->get($cacheKey);
        if ($cached !== null) {
            return response($cached['content'], 200)
                ->header('Content-Type', $cached['content_type']);
        }

        $response = $next($request);

        $content = $response->getContent();

        // Only cache successful responses with non-empty content
        if ($response->getStatusCode() === 200 && $content !== false && $content !== '') {
            Cache::store('file')->put($cacheKey, [
                'content'      => $content,
                'content_type' => $response->headers->get('Content-Type', 'application/json'),
            ], config('app.cache_indx', 60));
        }

        return $response;
    }
}
