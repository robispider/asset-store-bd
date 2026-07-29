<?php

namespace GovStore\Metadata\Support;

class HealthReport
{
    public array $providers = [];
    public int $totalModels = 0;
    public int $compliantModels = 0;
    public int $nonCompliantModels = 0;
    public array $nonCompliantModelDetails = [];
    public array $orphanMappings = [];
    public int $healthScore = 100;
}