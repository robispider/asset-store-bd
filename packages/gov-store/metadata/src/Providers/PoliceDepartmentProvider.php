<?php

namespace GovStore\Metadata\Providers;

use GovStore\Metadata\Contracts\MetadataProviderInterface;
use GovStore\Metadata\Support\LogicalField;

class PoliceDepartmentProvider implements MetadataProviderInterface
{
    public function getName(): string
    {
        return 'Police Department Context';
    }

    public function getVersion(): string
    {
        return 'v1';
    }

    public function getFields(): array
    {
        return [
            new LogicalField(
                'police.vehicle.radio_id',
                'Radio ID',
                'text',
                'Secured VHF communication transceiver registry identifier',
                true
            ),
        ];
    }

    public function supports(array $context): bool
    {
        $companyName = strtolower($context['company_name'] ?? '');
        return str_contains($companyName, 'police') || str_contains($companyName, 'home affairs');
    }
}