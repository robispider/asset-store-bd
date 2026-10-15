<?php

namespace GovStore\TenantScope\Http\Controllers\Concerns;

use Closure;
use GovStore\TenantScope\Exceptions\WorkflowRefusal;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Workflow refusals become a message under the field, with the person's input kept, instead of a bare
 * error page. Authorization failures and unknown objects still end as 403/404: only refusals that carry
 * a translated message are shown, and RequireGovAbility rolls back any response that flashes errors.
 * JSON callers keep the refusal's status code. Shared by office-membership and custom-requests (D8).
 */
trait RespondsWithRefusals
{
    /** The error key for refusals that name no field of their own (e.g. another package's 409). */
    protected function refusalFallbackField(): string
    {
        return 'workflow';
    }

    protected function attempt(callable $step, string|Closure $success, ?string $to = null, ?string $failTo = null): RedirectResponse
    {
        try {
            $step();
        } catch (WorkflowRefusal $refusal) {
            if (request()->expectsJson()) {
                throw $refusal;
            }

            return $this->refused($refusal->field, $refusal->getMessage(), $failTo);
        } catch (HttpExceptionInterface $e) {
            // Office intake paused, a stale role: other packages' 409s already carry a translated message.
            if ($e->getStatusCode() === 409 && $e->getMessage() !== '' && ! request()->expectsJson()) {
                return $this->refused($this->refusalFallbackField(), $e->getMessage(), $failTo);
            }
            throw $e;
        }

        return ($to ? redirect($to) : back())->with('success', $success instanceof Closure ? $success() : $success);
    }

    private function refused(string $field, string $message, ?string $to): RedirectResponse
    {
        return ($to ? redirect($to) : back())->withErrors([$field => $message])->withInput();
    }
}
