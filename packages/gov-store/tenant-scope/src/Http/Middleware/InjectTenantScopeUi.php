<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class InjectTenantScopeUi
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (auth()->check() && 
            $response instanceof Response && 
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