<?php

namespace GovStore\TenantScope\Exceptions;

use Exception;

class TenantBoundaryException extends Exception
{
    protected string $reasonCode;

    public function __construct(string $message, string $reasonCode = 'BOUNDARY', int $code = 403, Exception $previous = null)
    {
        $this->reasonCode = strtoupper($reasonCode);
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the structured semantic reason for auditing or JSON API error responses.
     */
    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }
}