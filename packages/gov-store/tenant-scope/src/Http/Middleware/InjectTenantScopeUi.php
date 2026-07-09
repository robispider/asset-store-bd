<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class InjectTenantScopeUi
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Only rewrite real, full HTML page responses. Skip AJAX (datatables/select2),
        // redirects, downloads and errors — no point rendering + rebuilding those bodies.
        if (auth()->check() &&
            !$request->ajax() &&
            $response instanceof Response &&
            $response->getStatusCode() === 200 &&
            str_contains($response->headers->get('Content-Type') ?? '', 'text/html')
        ) {
            $content = $response->getContent();
            $script = view('govscope::hooks.menu-injection')->render();

            $pos = strrpos($content, '</body>');
            if ($pos !== false) {
                $content = substr($content, 0, $pos) . $script . substr($content, $pos);
                $response->setContent($content);
            }
        }

        return $response;
    }
}