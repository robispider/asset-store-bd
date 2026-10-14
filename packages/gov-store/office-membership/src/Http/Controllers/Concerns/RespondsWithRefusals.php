<?php

namespace GovStore\OfficeMembership\Http\Controllers\Concerns;

use Closure;
use GovStore\OfficeMembership\Exceptions\MembershipRefused;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Workflow refusals become a message under the field, with the person's input kept (plan F1).
 * Authorization failures and unknown objects still end as 403/404: only refusals that carry a
 * translated message are shown, and RequireGovAbility rolls back any flashed-error response.
 */
trait RespondsWithRefusals
{
    protected function attempt(callable $step, string|Closure $success, ?string $to = null, ?string $failTo = null): RedirectResponse
    {
        try {
            $step();
        } catch (MembershipRefused $refusal) {
            return $this->refused($refusal->field, $refusal->getMessage(), $failTo);
        } catch (HttpExceptionInterface $e) {
            // Office intake paused, a stale role: other packages' 409s already carry a translated message.
            if ($e->getStatusCode() === 409 && $e->getMessage() !== '') {
                return $this->refused('membership', $e->getMessage(), $failTo);
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
