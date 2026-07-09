<?php

namespace GovStore\OfficeMembership\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class InjectMembershipUi
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Only run if the user is authenticated, and we are loading a standard HTML page
        if (auth()->check() && 
            $response instanceof Response && 
            str_contains($response->headers->get('Content-Type') ?? '', 'text/html')
        ) {
            $content = $response->getContent();

            // Render the membership package's dynamic hook
            $script = view('govmem::hooks.menu-injection')->render();

            $pos = strrpos($content, '</body>');
            if ($pos !== false) {
                $content = substr($content, 0, $pos) . $script . substr($content, $pos);
                $response->setContent($content);
            }
        }

        return $response;
    }
}