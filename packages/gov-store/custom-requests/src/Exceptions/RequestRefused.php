<?php

namespace GovStore\CustomRequests\Exceptions;

use GovStore\TenantScope\Exceptions\WorkflowRefusal;

/**
 * A request workflow refusal with a whole-sentence message and the form field it belongs to
 * (e.g. issue.{line} or items.{line}.qty). Status codes are unchanged (409 · 422), so callers and
 * JSON clients behave as before; controllers show the message in place instead of a bare error page.
 */
class RequestRefused extends WorkflowRefusal
{
    public function __construct(int $status, string $key, string $field = 'request', array $replace = [])
    {
        parent::__construct($status, __('requestlabels::requests.'.$key, $replace), $field);
    }

    public static function unless(bool $condition, int $status, string $key, string $field = 'request', array $replace = []): void
    {
        if (! $condition) {
            throw new self($status, $key, $field, $replace);
        }
    }

    public static function when(bool $condition, int $status, string $key, string $field = 'request', array $replace = []): void
    {
        self::unless(! $condition, $status, $key, $field, $replace);
    }
}
