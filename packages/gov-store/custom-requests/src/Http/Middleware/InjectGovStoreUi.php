<?php

namespace GovStore\CustomRequests\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class InjectGovStoreUi
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // We only inject if the user is logged in, and we are loading a standard HTML page
        if (auth()->check() && 
            $response instanceof Response && 
            str_contains($response->headers->get('Content-Type') ?? '', 'text/html')
        ) {
            $content = $response->getContent();

            // Render our jQuery injection script
            $script = view('govstore::hooks.menu-injection')->render();

            // Find the closing </body> tag and inject our script right before it
            $pos = strrpos($content, '</body>');
            if ($pos !== false) {
                $content = substr($content, 0, $pos) . $script . substr($content, $pos);
                $response->setContent($content);
            }
        }

        return $response;
    }
}