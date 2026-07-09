<?php

namespace GovStore\Organization\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class InjectOrganizationUi
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Inject only if user is logged in, and we are loading a standard HTML page
        if (auth()->check() && 
            $response instanceof Response && 
            str_contains($response->headers->get('Content-Type') ?? '', 'text/html')
        ) {
            $content = $response->getContent();

            // Render the organization package's hook view
            $script = view('govorg::hooks.menu-injection')->render();

            $pos = strrpos($content, '</body>');
            if ($pos !== false) {
                $content = substr($content, 0, $pos) . $script . substr($content, $pos);
                $response->setContent($content);
            }
        }

        return $response;
    }
}