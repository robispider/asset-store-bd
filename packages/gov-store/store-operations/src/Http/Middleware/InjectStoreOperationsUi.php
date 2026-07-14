<?php

namespace GovStore\StoreOperations\Http\Middleware;

use Closure;
use GovStore\StoreOperations\UI\TabRegistry;

class InjectStoreOperationsUi
{
    protected TabRegistry $tabRegistry;

    public function __construct(TabRegistry $tabRegistry)
    {
        $this->tabRegistry = $tabRegistry;
    }

    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($request->ajax() || $request->wantsJson()) {
            return $response;
        }

        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();

            if (str_contains($content, '</body>')) {
                // 1. Sidebar menu injection
                $viewHtml = view('storeops::hooks.menu-injection')->render();
                $content = str_replace('</body>', $viewHtml . '</body>', $content);

                // 2. Tab Registry injection
                $tabScript = $this->compileRegistryScript();
                if ($tabScript) {
                    $content = str_replace('</body>', $tabScript . '</body>', $content);
                }

                $response->setContent($content);
            }
        }

        return $response;
    }

    protected function compileRegistryScript(): ?string
    {
        $path = request()->getPathInfo();
        $match = preg_match('/\/(consumables|accessories|components)\/(\d+)/', $path, $matches);

        if (!$match) {
            return null;
        }

        $type = $matches[1];
        $singularType = strtolower(substr($type, 0, -1)); // e.g., "consumable"
        $id = $matches[2];

        $registeredTabs = $this->tabRegistry->getTabsFor($singularType);
        if (empty($registeredTabs)) {
            return null;
        }

        // Format tabs array to pass directly to JavaScript engine
        $tabsJson = json_encode(array_map(function ($tab) use ($singularType, $id) {
            return [
                'id' => $tab->id,
                'title' => $tab->title,
                'icon' => $tab->icon,
                'ajaxUrl' => str_replace(['{type}', '{id}'], [$singularType, $id], $tab->ajaxUrl),
            ];
        }, $registeredTabs));

        return '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            let registeredTabs = ' . $tabsJson . ';
            let tabContainer = document.querySelector(".nav-tabs");
            let paneContainer = document.querySelector(".tab-content");

            if (tabContainer && paneContainer && registeredTabs.length > 0) {
                registeredTabs.forEach(function(tab, index) {
                    // Inject Header
                    let iconHtml = tab.icon ? \'<i class="\' + tab.icon + \'"></i> \' : \'\';
                    let tabHtml = \'<li><a href="#\' + tab.id + \'" data-toggle="tab">\' + iconHtml + tab.title + \'</a></li>\';
                    tabContainer.insertAdjacentHTML("afterbegin", tabHtml);

                    // Inject Body Frame
                    let paneHtml = \'<div class="tab-pane" id="\' + tab.id + \'"><div id="target-\' + tab.id + \'" style="padding: 15px;"><i class="fa fa-spinner fa-spin"></i> Loading...</div></div>\';
                    paneContainer.insertAdjacentHTML("afterbegin", paneHtml);

                    // Fetch cleanly via standard AJAX headers with Redirect/Session guards
                    fetch(tab.ajaxUrl, {
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    })
                        .then(res => {
                            // Session Expiry Guard: If Laravel redirects to login, force full-window redirect
                            if (res.redirected) {
                                window.location.href = res.url;
                                return;
                            }
                            if (!res.ok) throw new Error();
                            return res.text();
                        })
                        .then(html => {
                            if (html) {
                                document.getElementById("target-" + tab.id).innerHTML = html;
                            }
                        })
                        .catch(() => {
                            document.getElementById("target-" + tab.id).innerHTML = \'<span class="text-danger">Failed to load content.</span>\';
                        });
                });

                // Shift default active tab focus to the newly injected workspace tab
                document.querySelectorAll(".nav-tabs li").forEach(el => el.classList.remove("active"));
                document.querySelectorAll(".tab-content .tab-pane").forEach(el => el.classList.remove("active"));

                tabContainer.querySelector("li:first-child").classList.add("active");
                paneContainer.querySelector(".tab-pane:first-child").classList.add("active");
            }
        });
        </script>
        ';
    }
}