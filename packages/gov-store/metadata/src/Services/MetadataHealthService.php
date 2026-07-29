<?php

namespace GovStore\Metadata\Services;

use App\Models\AssetModel;
use App\Models\CustomField;
use GovStore\Metadata\Registry\MetadataRegistry;
use GovStore\Metadata\Models\ModelMetadataState;
use GovStore\Metadata\Models\MetadataFieldMapping;
use GovStore\Metadata\Support\HealthReport;

class MetadataHealthService
{
    protected MetadataRegistry $registry;
    protected HierarchicalResolver $resolver;

    public function __construct(MetadataRegistry $registry, HierarchicalResolver $resolver)
    {
        $this->registry = $registry;
        $this->resolver = $resolver;
    }

    /**
     * Executes read-only validation passes to generate the platform report.
     */
    public function generateReport(): HealthReport
    {
        $report = new HealthReport();

        // 1. Gather configured provider details
        foreach ($this->registry->getProviders() as $provider) {
            $report->providers[] = [
                'name' => $provider->getName(),
                'version' => $provider->getVersion(),
                'fields_count' => count($provider->getFields()),
            ];
        }

        // 2. Assess active model validation alignments
        $models = AssetModel::withoutGlobalScopes()->get();
        $report->totalModels = $models->count();

        foreach ($models as $model) {
            $compliant = $this->evaluateModelCompliance($model);
            if ($compliant) {
                $report->compliantModels++;
            } else {
                $report->nonCompliantModels++;
                $report->nonCompliantModelDetails[] = [
                    'id' => $model->id,
                    'name' => $model->name,
                ];
            }
        }

        // Calculate score
        if ($report->totalModels > 0) {
            $report->healthScore = (int) (($report->compliantModels / $report->totalModels) * 100);
        }

        // 3. Inspect physical integrity constraints for orphan mappings
        $mappings = MetadataFieldMapping::all();
        foreach ($mappings as $mapping) {
            $fieldExists = CustomField::where('id', $mapping->custom_field_id)->exists();
            if (!$fieldExists) {
                $report->orphanMappings[] = [
                    'identifier' => $mapping->identifier,
                    'custom_field_id' => $mapping->custom_field_id,
                    'reason' => 'Associated Custom Field has been deleted in Snipe-IT core.',
                ];
            }
        }

        return $report;
    }

    /**
     * Measures if a single model aligns perfectly with active schema versions.
     */
    protected function evaluateModelCompliance(AssetModel $assetModel): bool
    {
        $context = [
            'model_id'      => $assetModel->id,
            'category_id'   => $assetModel->category_id,
            'category_name' => $assetModel->category ? $assetModel->category->name : null,
            'company_name'  => null,
        ];

        if (app()->bound(\GovStore\TenantScope\Contexts\TenantContext::class)) {
            $tenantContext = app(\GovStore\TenantScope\Contexts\TenantContext::class);
            if ($tenantContext->isActive) {
                if ($assetModel->category && $assetModel->category->company) {
                    $context['company_name'] = $assetModel->category->company->name;
                }
            }
        }

        $applicableProviders = [];
        foreach ($this->registry->getProviders() as $provider) {
            if ($provider->supports($context)) {
                $applicableProviders[$provider->getName()] = $provider;
            }
        }

        $currentStates = ModelMetadataState::where('model_id', $assetModel->id)
            ->pluck('version', 'provider_name')
            ->toArray();

        foreach ($applicableProviders as $name => $provider) {
            if (!isset($currentStates[$name]) || $currentStates[$name] !== $provider->getVersion()) {
                return false;
            }
        }

        foreach (array_keys($currentStates) as $stateProviderName) {
            if (!isset($applicableProviders[$stateProviderName])) {
                return false;
            }
        }

        return true;
    }
}