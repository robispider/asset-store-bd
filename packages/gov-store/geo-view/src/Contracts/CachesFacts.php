<?php

namespace GovStore\GeoView\Contracts;

/**
 * Opt-in server cache of a source's facts for (source, params, filters, scope) — plan §6.6.
 * Successive drills then reuse one computation of the programme.
 */
interface CachesFacts
{
    /** Seconds; 0 disables the cache. */
    public function factsTtl(): int;
}
