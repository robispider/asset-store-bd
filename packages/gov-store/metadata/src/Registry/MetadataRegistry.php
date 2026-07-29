<?php

namespace GovStore\Metadata\Registry;

use GovStore\Metadata\Contracts\MetadataProviderInterface;

class MetadataRegistry
{
    /**
     * @var MetadataProviderInterface[]
     */
    protected array $providers = [];

    /**
     * Register a new metadata provider definition.
     */
    public function register(MetadataProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    /**
     * Retrieve all registered providers.
     *
     * @return MetadataProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
