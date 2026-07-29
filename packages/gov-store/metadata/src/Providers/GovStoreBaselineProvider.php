<?php

namespace GovStore\Metadata\Providers;

use GovStore\Metadata\Contracts\MetadataProviderInterface;
use GovStore\Metadata\Support\LogicalField;

class GovStoreBaselineProvider implements MetadataProviderInterface
{
    public function getName(): string
    {
        return 'GovStore Baseline';
    }

    public function getVersion(): string
    {
        return 'v1';
    }

    public function getFields(): array
    {
        return [
            new LogicalField('govstore.baseline.grn', 'GRN', 'text', 'Government Receipt Note number', true),
            new LogicalField('govstore.baseline.funding_source', 'Funding Source', 'text', 'Source of funding or project details', false),
            new LogicalField('govstore.baseline.allocation', 'Allocation', 'text', 'Allotment reference details', false),
        ];
    }

    public function supports(array $context): bool
    {
        // Global Baseline is always required
        return true;
    }
}