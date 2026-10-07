<?php

namespace GovStore\Theming\Http\Controllers;

use GovStore\TenantScope\Services\AccessAudit;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

abstract class Controller extends BaseController
{
    /** tenant-scope's standard access-denied page (or a plain 403 when tenant-scope is absent). */
    protected function deny(Request $request, string $ability): Response
    {
        try {
            $access = app(GovAccess::class);
            $decision = $access->decide($request->user(), $ability);
            $reference = app(AccessAudit::class)->record($decision, 'denied');
            $payload = $access->payload($decision, $reference);

            return $request->expectsJson()
                ? response()->json($payload, 403)
                : response()->view('govscope::access.denied', compact('payload'), 403);
        } catch (Throwable) {
            abort(403);
        }
    }
}
