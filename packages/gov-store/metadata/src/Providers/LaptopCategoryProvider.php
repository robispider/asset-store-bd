<?php

namespace GovStore\Metadata\Providers;

use GovStore\Metadata\Contracts\MetadataProviderInterface;
use GovStore\Metadata\Support\LogicalField;

class LaptopCategoryProvider implements MetadataProviderInterface
{
    public function getName(): string
    {
        return 'Laptop Specific Addon';
    }

    public function getVersion(): string
    {
        return 'v1';
    }

    public function getFields(): array
    {
        return [
            new LogicalField(
                'hardware.laptop.cpu_generation',
                'CPU Generation',
                'text',
                'Processor generation specifications (e.g., Core i7 13th Gen)',
                true
            ),
        ];
    }

    public function supports(array $context): bool
    {
        $categoryName = strtolower($context['category_name'] ?? '');
        return str_contains($categoryName, 'laptop');
    }
}