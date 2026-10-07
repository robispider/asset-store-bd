<?php

namespace GovStore\TenantScope\Exceptions;

use Exception;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TenantBoundaryException extends Exception implements HttpExceptionInterface
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

    public function getStatusCode(): int
    {
        return in_array($this->getCode(), [403, 404, 409, 422], true) ? $this->getCode() : 403;
    }

    public function getHeaders(): array
    {
        return [];
    }
}
