<?php

namespace GovStore\StoreOperations\UI;

class TabRegistry
{
    protected array $registrations = [];

    /**
     * Registers a modular Tab to be rendered on target core view templates.
     */
    public function registerTab(string $targetModelBasename, Tab $tab): void
    {
        $this->registrations[strtolower($targetModelBasename)][] = $tab;
    }

    /**
     * Fetch registered UI additions for a given entity type
     */
    public function getTabsFor(string $targetModelBasename): array
    {
        return $this->registrations[strtolower($targetModelBasename)] ?? [];
    }
}
