<?php

namespace GovStore\CustomRequests\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class InjectGovStoreUi
{
 
      /**
     * Inject the custom floating basket widget and product page buttons.
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($request->ajax() || $request->wantsJson()) {
            return $response;
        }

        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();

            if (str_contains($content, '</body>')) {
                // Renders the clean, decoupled basket widgets
                $widgetHtml = view('govstore::hooks.basket-widget')->render();
                
                $content = str_replace('</body>', $widgetHtml . '</body>', $content);
                $response->setContent($content);
            }
        }

        return $response;
    }
}