<?php

namespace GovStore\Metadata\Services;

use App\Models\AssetModel;
use GovStore\Metadata\Registry\MetadataRegistry;
use GovStore\Metadata\Models\ModelMetadataState;
use GovStore\Metadata\Compiler\MetadataCompiler;
use Illuminate\Support\Facades\DB;

class ConvergenceEngine
{
    protected HierarchicalResolver $resolver;
    protected MetadataCompiler $compiler;
    protected MetadataRegistry $registry;

    public function __construct(
        HierarchicalResolver $resolver,
        MetadataCompiler $compiler,
        MetadataRegistry $registry
    ) {
        $this->resolver = $resolver;
        $this->compiler = $compiler;
        $this->registry = $registry;
    }

    /**
     * Measures model schema alignment and performs on-the-fly reconciliation if out of sync.
     */
    public function converge(AssetModel $assetModel): bool
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

        $isCompliant = true;

        foreach ($applicableProviders as $name => $provider) {
            if (!isset($currentStates[$name]) || $currentStates[$name] !== $provider->getVersion()) {
                $isCompliant = false;
                break;
            }
        }

        if ($isCompliant) {
            foreach (array_keys($currentStates) as $stateProviderName) {
                if (!isset($applicableProviders[$stateProviderName])) {
                    $isCompliant = false;
                    break;
                }
            }
        }

        if ($isCompliant) {
            return false;
        }

        // Single, explicit transaction boundary to prevent sub-transaction collapse
        DB::transaction(function () use ($assetModel, $applicableProviders) {
            $logicalSchema = $this->resolver->resolve($assetModel);
            $fieldsetName = "Compiled Schema: " . $assetModel->name;
            
            // Compiles safely inside the current transaction
            $fieldset = $this->compiler->compile($fieldsetName, $logicalSchema);

            $assetModel->fieldset_id = $fieldset->id;
            $assetModel->saveQuietly();

            ModelMetadataState::where('model_id', $assetModel->id)->delete();
            foreach ($applicableProviders as $provider) {
                ModelMetadataState::create([
                    'model_id'      => $assetModel->id,
                    'provider_name' => $provider->getName(),
                    'version'       => $provider->getVersion(),
                ]);
            }
        });

        return true;
    }
}