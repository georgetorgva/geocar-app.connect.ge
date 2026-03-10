<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     */
    protected function shouldSkip($request): bool
    {

        print 111111;
        // Skip CSRF for all routes starting with these prefixes
        $skip = [
            'api/admin/*',
            'api/*',               // if you want to skip all api routes
        ];

        foreach ($skip as $pattern) {
            // Convert Laravel route pattern to regex
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
