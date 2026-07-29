<?php

namespace GovStore\Metadata\Contracts;

interface MetadataProviderInterface
{
    public function getName(): string;
    public function getVersion(): string;

    /**
     * Get the list of logical fields requested by this provider.
     *
     * @return \GovStore\Metadata\Support\LogicalField[]
     */
    public function getFields(): array;

    /**
     * Evaluate if this provider's metadata is applicable within the given context.
     *
     * @param array $context Contains metadata indicators (e.g. category_name, company_name)
     */
    public function supports(array $context): bool;
}