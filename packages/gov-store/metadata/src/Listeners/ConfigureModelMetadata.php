<?php

namespace GovStore\Metadata\Listeners;

use GovStore\Metadata\Events\AssetModelCreated;
use GovStore\Metadata\Services\ConvergenceEngine;

class ConfigureModelMetadata
{
    protected ConvergenceEngine $engine;

    public function __construct(ConvergenceEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Invokes convergence, ensuring state configurations are logged immediately upon creation.
     */
    public function handle(AssetModelCreated $event): void
    {
        $this->engine->converge($event->assetModel);
    }
}