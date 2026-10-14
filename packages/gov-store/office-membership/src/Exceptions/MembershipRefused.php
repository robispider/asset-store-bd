<?php

namespace GovStore\OfficeMembership\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A workflow refusal the person can act on: a wrong code, an outdated page, an unfinished checklist.
 * It keeps the HTTP status the workflow has always used (404 · 409 · 422), so service callers and the
 * G1 handler behave as before, and carries a translated message plus the form field it belongs to,
 * so controllers can send the person back to the form with their input kept.
 */
class MembershipRefused extends HttpException
{
    public function __construct(int $status, string $key, public readonly string $field = 'membership', array $replace = [])
    {
        parent::__construct($status, __('office_membership::member.'.$key, $replace));
    }

    public static function unless(bool $condition, int $status, string $key, string $field = 'membership', array $replace = []): void
    {
        if (! $condition) {
            throw new self($status, $key, $field, $replace);
        }
    }

    public static function when(bool $condition, int $status, string $key, string $field = 'membership', array $replace = []): void
    {
        self::unless(! $condition, $status, $key, $field, $replace);
    }
}
