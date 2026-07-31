<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;

class RequireProgrammeTrackingCapability implements CapabilityInterface
{
    public function getRequirements(array $config = []): array
    {
        return [];
    }

    public function validate(array $data, array $config = []): array
    {
        return [];
    }

    public function execute(object $item, array $config = []): void
    {
        // Tracking logic is handled via events inside the PostingPipelineManager
    }

    public function renderUI(object $item = null, array $config = []): string
    {
        // UI is handled globally at the Document Header level via Handshakes
        return '';
    }
}