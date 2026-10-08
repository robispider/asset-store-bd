<?php

namespace GovStore\Tracking\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SafeTrackingResponse
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }
        $mutating = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
        $level = DB::transactionLevel();
        if ($mutating) {
            DB::beginTransaction();
        }
        try {
            $response = $next($request);
            if ($mutating) {
                if ($response->getStatusCode() >= 400 || in_array('error', $request->session()->get('_flash.new', []), true)
                    || in_array('errors', $request->session()->get('_flash.new', []), true)) {
                    DB::rollBack($level);
                } else {
                    DB::commit();
                }
            }
            return $response;
        } catch (ValidationException $e) {
            if ($mutating && DB::transactionLevel() > $level) DB::rollBack($level);
            return $request->expectsJson()
                ? response()->json(['message' => __('govtracking::general.invalid_input'), 'errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput($request->except(['document', 'order_pdf', '_token']));
        } catch (\Throwable $e) {
            if ($mutating && DB::transactionLevel() > $level) DB::rollBack($level);
            $reference = (string) Str::uuid();
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : ($e instanceof ModelNotFoundException ? 404 : 500);
            Log::error('Tracking request failed', ['reference_id' => $reference, 'exception' => $e]);
            $message = __('govtracking::general.safe_failure', ['reference' => $reference]);
            return $request->expectsJson()
                ? response()->json(['message' => $message, 'reference_id' => $reference], $status)
                : response()->view('govtracking::error', compact('message', 'reference'), $status);
        }
    }
}
