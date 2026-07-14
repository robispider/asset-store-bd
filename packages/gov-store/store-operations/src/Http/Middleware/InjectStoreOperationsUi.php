<?php

namespace GovStore\StoreOperations\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class InjectStoreOperationsUi
{
    /**
     * Parse and inject custom sidebar elements to HTML output streams.
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Bypass API/Ajax calls or non-HTML responses
        if ($request->ajax() || $request->wantsJson()) {
            return $response;
        }

        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();

            if (str_contains($content, '</body>')) {
                // Render the compiled blade view containing JavaScript & Role Checks
                $viewHtml = view('storeops::hooks.menu-injection')->render();

                // Inject hook instantly
                $content = str_replace('</body>', $viewHtml . '</body>', $content);
                $response->setContent($content);
            }
        }

        return $response;
    }
}
