<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The site's language comes from the route group, not from the browser:
 * every language has its own URLs for search engines to index.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next, string $locale): Response
    {
        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
