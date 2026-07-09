<?php

namespace GovStore\CustomRequests\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class InjectGovStoreUi
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