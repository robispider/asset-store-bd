<?php

namespace GovStore\StoreOperations\Http\Middleware;

use App\Helpers\Helper;
use Closure;
use GovStore\StoreOperations\Services\IntakePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * GRN-only intake, pushed onto the `web` and `api` groups (no Snipe-IT route file is edited).
 * Works on route names, so it applies to super users too.
 */
class EnforceGrnIntake
{
    public function __construct(private IntakePolicy $policy) {}

    public function handle(Request $request, Closure $next)
    {
        $name = $request->route()?->getName();
        if (! $name || ! $this->policy->active()) {
            return $next($request);
        }

        if ($this->policy->blocks($name)) {
            return $this->deny($request, __('storeops::intake.direct_create_blocked'));
        }

        if ($this->policy->requiresZeroQuantity($name) && (int) $request->input('qty', 0) > 0) {
            return $this->deny($request, __('storeops::intake.record_starts_at_zero'), back: true);
        }

        if ($this->policy->isStockImport($name, $request->input('import-type'))) {
            // Snipe-IT's importer calls the API with the session cookie; token clients use the api guard.
            $user = $request->user() ?? $request->user('api');
            if (! $user?->isSuperUser()) {
                return $this->importErrors(__('storeops::intake.import_super_only'));
            }
            $this->policy->watchImport(true);
            try {
                $response = $next($request);
            } finally {
                $this->policy->watchImport(false);
            }

            return ($violation = $this->policy->importViolation()) ? $this->importErrors($violation) : $response;
        }

        return $next($request);
    }

    private function deny(Request $request, string $message, bool $back = false): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $message), 403);
        }
        if (! $back && Gate::allows('storeops.documents.view')) {
            return redirect()->route('storeops.hub')->with('error', $message);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }

    /** Snipe-IT's importer UI reads errors in this shape. */
    private function importErrors(string $message): Response
    {
        return response()->json(Helper::formatStandardApiResponse('import-errors', null, [[$message]]), 500);
    }
}
