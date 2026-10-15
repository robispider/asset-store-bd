<?php

namespace GovStore\TenantScope\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A workflow refusal the person can act on: not enough stock, someone else decided first, a stale page.
 * It keeps the HTTP status the workflow has always used (409 · 422), so service callers, JSON clients and
 * the G1 handler behave as before, and carries a translated message plus the form field it belongs to,
 * so RespondsWithRefusals can send the person back to the form with the message in place and input kept.
 * Packages extend it with their own translation namespace (MembershipRefused, RequestRefused).
 */
class WorkflowRefusal extends HttpException
{
    public readonly string $field;

    public function __construct(int $status, string $message, string $field = 'workflow')
    {
        $this->field = $field;
        parent::__construct($status, $message);
    }
}
