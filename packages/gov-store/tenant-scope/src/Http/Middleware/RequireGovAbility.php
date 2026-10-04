<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use GovStore\TenantScope\Services\AccessAudit;
use GovStore\TenantScope\Services\GovAccess;
use GovStore\TenantScope\Services\NationalChangeReview;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class RequireGovAbility
{
    public function __construct(private GovAccess $access, private AccessAudit $audit) {}

    public function handle($request, Closure $next, string $ability)
    {
        // Debug toolbar payloads contain SQL and exceptions even on a safe denial.
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }
        try {
            return $this->process($request, $next, $ability);
        } catch (ValidationException $e) {
            throw $e;
        } catch (ModelNotFoundException $e) {
            abort(404);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $exception) {
            $reference = (string) Str::uuid();
            Log::error('GovStore action failed', ['reference_id' => $reference, 'exception' => $exception]);
            $message = __('tenantops::access.failed', ['reference' => $reference]);
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => $message, 'reference_id' => $reference], 500);
            }

            return response()->view('govscope::access.failure', compact('message', 'reference'), 500);
        }
    }

    private function process($request, Closure $next, string $ability)
    {
        $decision = $this->access->decide($request->user(), $ability);
        if (! $decision->allowed) {
            $enforce = $this->access->enforces($ability) || $decision->reason === 'unknown_ability';
            $reference = $this->audit->record($decision, $enforce ? 'denied' : 'shadow');
            if ($enforce) {
                $payload = $this->access->payload($decision, $reference);

                return $request->expectsJson() || $request->ajax()
                    ? response()->json($payload, 403)
                    : response()->view('govscope::access.denied', compact('payload'), 403);
            }
        }
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }
        $definition = config('govstore-abilities', [])[$ability] ?? [];
        if ($definition['national'] ?? false) {
            $review = app(NationalChangeReview::class)->handle($request, $ability);
            if ($review) {
                return $review;
            }
        }

        // Mutation and its audit commit together; never record submitted form payloads.
        DB::beginTransaction();
        try {
            $response = $next($request);
            $newFlash = $request->hasSession() ? $request->session()->get('_flash.new', []) : [];
            $failed = $response->getStatusCode() >= 400 || (bool) array_intersect(['error', 'errors'], $newFlash);
            // Laravel's routing pipeline can render exceptions before they reach
            // this middleware. A rendered failure must still roll back writes.
            if ($failed) {
                DB::rollBack();
                $this->audit->record($decision, 'failed', $request->input('change_reason'));
            } else {
                $this->audit->record($decision, 'allowed', $request->input('change_reason'));
                DB::commit();
            }

            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
