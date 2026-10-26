<?php

namespace GovStore\Metadata\Services;

use GovStore\Metadata\Registry\MetadataRegistry;
use Illuminate\Support\Facades\DB;

class MetadataMaintenancePlan
{
    public function snapshot(): array
    {
        $providers = [];
        foreach (app(MetadataRegistry::class)->getProviders() as $provider) {
            $providers[$provider->getName()] = $provider->getVersion();
        }
        ksort($providers);
        $models = DB::table('models')->whereNull('deleted_at')->orderBy('id')
            ->get(['id', 'name', 'category_id', 'fieldset_id', 'updated_at']);

        return ['providers' => $providers, 'models' => $models->map(fn ($row) => (array) $row)->all(),
            'categories' => DB::table('categories')->whereIn('id', $models->pluck('category_id'))->orderBy('id')
                ->get(['id', 'name', 'category_type', 'updated_at'])->map(fn ($row) => (array) $row)->all()];
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->snapshot(), JSON_THROW_ON_ERROR));
    }
}
