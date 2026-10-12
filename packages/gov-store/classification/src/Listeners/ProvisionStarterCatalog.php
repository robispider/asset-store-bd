<?php

namespace GovStore\Classification\Listeners;

use GovStore\Classification\Services\OfficeStarterCatalog;
use GovStore\Organization\Events\OfficeProvisioned;

class ProvisionStarterCatalog
{
    public function handle(OfficeProvisioned $event): void
    {
        app(OfficeStarterCatalog::class)->schedule($event);
    }
}
