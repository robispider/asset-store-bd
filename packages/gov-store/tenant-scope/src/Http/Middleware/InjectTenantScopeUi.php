<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class InjectTenantScopeUi
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($request->ajax() || $request->wantsJson()) {
            return $response;
        }

        if (
            auth()->check() &&
            $response instanceof Response &&
            str_contains($response->headers->get('Content-Type') ?? '', 'text/html')
        ) {
            $content = $response->getContent();

            if (str_contains($content, '</body>')) {
                // Render the single, unified GovStore menu from the Central Menu Registry
                $menuHtml = view('govscope::hooks.unified-menu')->render();
                $content  = str_replace('</body>', $menuHtml . '</body>', $content);

                $response->setContent($content);
            }
        }

        return $response;
    }
}