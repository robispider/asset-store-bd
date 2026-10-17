<?php

namespace GovStore\Metadata\Support;

use GovStore\Metadata\Models\MetadataValue;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Someone else saved first. Carries the current row so the editor can compare and choose. */
class StaleMetadataRevision extends HttpException
{
    public function __construct(public ?MetadataValue $current)
    {
        parent::__construct(409, __('govmeta::metadata.stale_revision'));
    }
}
