<?php

namespace GovStore\StoreOperations\Services;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use GovStore\StoreOperations\Enums\AssignmentScope;
use GovStore\StoreOperations\Enums\PolicyStatus;
use GovStore\StoreOperations\Models\Profile;
use GovStore\StoreOperations\Models\ProfileAssignment;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Collection;

/**
 * Explains the rule engine for people: which level set each rule, which level replaced it and
 * what the storekeeper finally gets. It reads the same layers as ProfileCompilerService
 * (activeLayers) and merges them the same way, so the Rules studio never disagrees with posting.
 */
class RuleTraceService
{
    /** Merge order, lowest precedence first. DEFAULT = package defaults (CapabilityRegistry::extend). */
    public const LAYERS = ['DEFAULT', 'GLOBAL', 'COMPANY', 'LOCATION', 'CATEGORY', 'MODEL'];

    /** Product type whose package defaults apply when a category of this kind is received. */
    private const RECEIVED_AS = ['asset' => 'assetmodel', 'consumable' => 'consumable', 'accessory' => 'accessory', 'component' => 'component'];

    private ?Collection $active = null;

    /**
     * The layer-by-layer story for one context. Company comes from the office when an office is
     * chosen, and category from the model when a model is chosen, exactly as posting resolves them.
     */
    public function trace(?Company $company, ?Location $location, ?Category $category, ?AssetModel $model): array
    {
        if ($location && $location->company_id) {
            $company = Company::find($location->company_id) ?? $company;
        }
        if ($model && $model->category_id) {
            $category = Category::find($model->category_id) ?? $category;
        }
        $context = clone app(TenantContext::class);
        $context->companyId = $company?->id;
        $context->locationId = $location?->id;
        $assignments = (new ProfileCompilerService($context))
            ->activeLayers($company?->id, $location?->id, $category?->id, $model?->id);

        $productType = $model ? 'assetmodel' : (self::RECEIVED_AS[$category?->category_type] ?? 'category');
        $targets = ['GLOBAL' => __('storeops::studio.whole_country'), 'COMPANY' => $company?->name,
            'LOCATION' => $location?->name, 'CATEGORY' => $category?->name, 'MODEL' => $model?->name];

        $layers = [$this->defaultLayer($productType)];
        foreach ($assignments as $key => $assignment) {
            $layers[] = $this->layer($key, $targets[$key], $assignment);
        }

        return ['layers' => $layers, 'rules' => $this->rules($layers), 'product_type' => $productType,
            'company' => $company, 'location' => $location, 'category' => $category, 'model' => $model];
    }

    /** Folds layers into code => [behavior, config, layer], with the engine's merge semantics. */
    public function fold(array $layers): array
    {
        $result = [];
        foreach ($layers as $layer) {
            foreach ($layer['rules'] as $code => $rule) {
                $result[$code] = $rule['behavior'] === 'ENFORCE'
                    ? ['behavior' => 'ENFORCE', 'config' => array_merge($result[$code]['config'] ?? [], $rule['config']), 'layer' => $layer['key']]
                    : ['behavior' => 'DISABLE', 'config' => [], 'layer' => $layer['key']];
            }
        }

        return $result;
    }

    /**
     * What a level changes compared with what it receives. Repeating the inherited value is not
     * a change; it is reported as 'same' so the editor can say it only matters if a parent changes.
     */
    public function diff(array $rules, array $inherited): array
    {
        $dictionary = CapabilityRegistry::getDictionary();
        $out = [];
        foreach ($dictionary as $code => $info) {
            if (! isset($rules[$code])) {
                continue;
            }
            $to = $rules[$code];
            $from = $inherited[$code] ?? null;
            $kind = match (true) {
                $from && $from['behavior'] === $to['behavior'] && ($to['behavior'] === 'DISABLE' || array_merge($from['config'], $to['config']) == $from['config']) => 'same',
                $to['behavior'] === 'DISABLE' => 'off',
                $from && $from['behavior'] === 'ENFORCE' => 'change',
                default => 'add',
            };
            $out[] = ['kind' => $kind, 'code' => $code, 'name' => $info['name'],
                'from' => $from ? $this->label($code, $from['behavior'], $from['config']) : null,
                'to' => $this->label($code, $to['behavior'], $to['behavior'] === 'ENFORCE' ? array_merge($from['config'] ?? [], $to['config']) : [])];
        }

        return $out;
    }

    /** "Required", "Required · 24 months", "On" or "Off" for one rule value. */
    public function label(string $code, ?string $behavior, array $config = []): string
    {
        if ($behavior === null) {
            return str_starts_with($code, 'require_') ? __('storeops::studio.not_required') : __('storeops::studio.not_on');
        }
        if ($behavior === 'DISABLE') {
            return __('storeops::studio.off');
        }
        $label = str_starts_with($code, 'require_') ? __('storeops::studio.required') : __('storeops::studio.on');
        if ($code === 'require_warranty' && isset($config['warranty_months'])) {
            $label .= ' · '.__('storeops::studio.months', ['months' => (int) $config['warranty_months']]);
        }

        return $label;
    }

    /** Rule map (code => behavior, config) of one policy; INHERIT rows say nothing. */
    public function rulesOf(?Profile $profile): array
    {
        $rules = [];
        foreach ($profile?->capabilities ?? [] as $capability) {
            $behavior = $capability->behavior instanceof \BackedEnum ? $capability->behavior->value : (string) $capability->behavior;
            if ($behavior !== 'INHERIT') {
                $rules[$capability->capability_code] = ['behavior' => $behavior, 'config' => (array) ($capability->config_payload ?? [])];
            }
        }

        return $rules;
    }

    /**
     * Organisation tree: the country, ministries that have offices or a policy, and their offices.
     * Each node lists what its policy changes compared with its parent.
     */
    public function orgTree(?int $selectedCompanyId = null): array
    {
        $global = $this->activeFor('GLOBAL', 1);
        $base = $this->fold([$this->defaultLayer('category'), $this->nodeLayer('GLOBAL', $global)]);
        $locations = Location::query()->orderBy('name')->get(['id', 'name', 'company_id']);
        $byCompany = $locations->groupBy(fn ($l) => (int) $l->company_id);
        $withPolicy = $this->active()->filter(fn ($a) => $a->target_type === 'Tenant')->pluck('target_id')->all();
        $companyIds = $byCompany->keys()->filter()->merge($withPolicy)->push($selectedCompanyId)->filter()->unique();
        $companies = Company::query()->whereIn('id', $companyIds)->orderBy('name')->get(['id', 'name']);

        $offices = function ($list, array $inherited) {
            return $list->map(function ($location) use ($inherited) {
                $assignment = $this->activeFor('LOCATION', $location->id);

                return $this->node('location', $location->id, $location->name, $assignment, $inherited);
            })->values()->all();
        };

        $tree = [];
        foreach ($companies as $company) {
            $assignment = $this->activeFor('COMPANY', $company->id);
            $node = $this->node('company', $company->id, $company->name, $assignment, $base);
            $inherited = $this->fold([$this->asLayer($base), $this->nodeLayer('COMPANY', $assignment)]);
            $node['children'] = $offices($byCompany->get($company->id, collect()), $inherited);
            $tree[] = $node;
        }

        return [
            'global' => $this->node('global', 1, __('storeops::studio.whole_country'), $global, $this->fold([$this->defaultLayer('category')])),
            'companies' => $tree,
            // Offices with no ministry, or one that no longer exists, get no ministry layer.
            'unassigned_offices' => $offices($locations->reject(fn ($l) => $companies->contains('id', (int) $l->company_id)), $base),
            'hidden_companies' => max(0, Company::query()->count() - $companies->count()),
        ];
    }

    /**
     * Product tree: categories by kind, each with the models that carry their own policy.
     * Changes are measured against what the chosen office already gives.
     */
    public function productTree(array $officeLayers, ?int $selectedModelId = null): array
    {
        $officeOnly = array_values(array_filter($officeLayers, fn ($l) => in_array($l['key'], ['GLOBAL', 'COMPANY', 'LOCATION'], true)));
        $modelPolicies = $this->active()->filter(fn ($a) => $a->target_type === 'App\Models\AssetModel')->pluck('target_id')->all();
        $models = AssetModel::query()->whereIn('id', array_filter(array_merge($modelPolicies, [$selectedModelId])))
            ->orderBy('name')->get(['id', 'name', 'category_id'])->groupBy('category_id');
        $modelCounts = AssetModel::query()->selectRaw('category_id, count(*) as aggregate')->groupBy('category_id')->pluck('aggregate', 'category_id');

        $groups = [];
        foreach (Category::query()->whereIn('category_type', array_keys(self::RECEIVED_AS))->orderBy('name')->get(['id', 'name', 'category_type']) as $category) {
            $inherited = $this->fold(array_merge([$this->defaultLayer(self::RECEIVED_AS[$category->category_type])], $officeOnly));
            $assignment = $this->activeFor('CATEGORY', $category->id);
            $node = $this->node('category', $category->id, $category->name, $assignment, $inherited);
            $below = $this->fold([$this->asLayer($inherited), $this->nodeLayer('CATEGORY', $assignment)]);
            $node['children'] = $models->get($category->id, collect())->map(function ($model) use ($below) {
                return $this->node('model', $model->id, $model->name, $this->activeFor('MODEL', $model->id), $below);
            })->values()->all();
            $node['model_count'] = (int) ($modelCounts[$category->id] ?? 0);
            $groups[$category->category_type][] = $node;
        }

        return $groups;
    }

    /** The published assignments of a policy's lineage, so a draft shows where it will apply. */
    public function attachments(Profile $policy): Collection
    {
        $ids = $policy->lineage_id
            ? Profile::query()->where('lineage_id', $policy->lineage_id)->pluck('id')
            : collect([$policy->id]);

        return ProfileAssignment::query()->whereIn('profile_id', $ids)
            ->where('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->orderBy('id')->get();
    }

    /**
     * Readable name of an assignment's target. target() is a plain morphTo and 'System', 'Tenant'
     * and 'Location' are not classes (nor morph-map aliases, which would change how Snipe-IT
     * stores every Location morph), so it is never eager-loaded.
     */
    public function targetName(ProfileAssignment $assignment): string
    {
        $class = ['COMPANY' => Company::class, 'LOCATION' => Location::class, 'CATEGORY' => Category::class, 'MODEL' => AssetModel::class][$this->layerOf($assignment)] ?? null;

        return $class ? ($class::find($assignment->target_id)?->name ?? '#'.$assignment->target_id) : __('storeops::studio.whole_country');
    }

    /** Layer key of an assignment: the engine's scope plus target kind. */
    public function layerOf(ProfileAssignment $assignment): string
    {
        $scope = $assignment->scope_level instanceof AssignmentScope ? $assignment->scope_level->value : (string) $assignment->scope_level;

        return match ($scope) {
            'GLOBAL' => 'GLOBAL',
            'COMPANY' => 'COMPANY',
            'LOCATION' => 'LOCATION',
            default => $assignment->target_type === 'App\Models\AssetModel' ? 'MODEL' : 'CATEGORY',
        };
    }

    private function rules(array $layers): array
    {
        $out = [];
        foreach (CapabilityRegistry::getDictionary() as $code => $info) {
            $setters = array_values(array_filter($layers, fn ($l) => isset($l['rules'][$code])));
            $decider = $setters ? end($setters)['key'] : null;
            $cells = [];
            $carry = null;
            $config = [];
            foreach ($layers as $layer) {
                $rule = $layer['rules'][$code] ?? null;
                if ($rule) {
                    $config = $rule['behavior'] === 'ENFORCE' ? array_merge($config, $rule['config']) : [];
                    $carry = $rule['behavior'] === 'ENFORCE' ? 'on' : 'off';
                    $cells[$layer['key']] = ['state' => $carry, 'role' => $layer['key'] === $decider ? 'decider' : 'replaced',
                        'value' => $this->label($code, $rule['behavior'], $config)];
                } else {
                    $cells[$layer['key']] = ['state' => $carry ? 'pass' : 'empty', 'carry' => $carry, 'role' => null, 'value' => null];
                }
            }
            $final = $this->fold($layers)[$code] ?? null;
            $out[$code] = [
                'code' => $code, 'name' => $info['name'], 'desc' => $info['desc'], 'group' => $info['group'] ?? 'General',
                'cells' => $cells, 'decider' => $decider,
                'replaced' => array_values(array_map(fn ($l) => $l['key'], array_slice($setters, 0, -1))),
                'result' => $final ? ($final['behavior'] === 'ENFORCE' ? 'on' : 'off') : 'none',
                'value' => $this->label($code, $final['behavior'] ?? null, $final['config'] ?? []),
            ];
        }

        return $out;
    }

    private function defaultLayer(string $productType): array
    {
        $rules = [];
        foreach (CapabilityRegistry::defaultsFor($productType) as $code) {
            $rules[$code] = ['behavior' => 'ENFORCE', 'config' => []];
        }

        return ['key' => 'DEFAULT', 'target' => __('storeops::studio.installed_packages'), 'chosen' => true,
            'assignment_id' => null, 'profile' => null, 'rules' => $rules];
    }

    private function layer(string $key, ?string $target, ?ProfileAssignment $assignment): array
    {
        $profile = $assignment?->profile;

        return ['key' => $key, 'target' => $target, 'chosen' => $target !== null, 'assignment_id' => $assignment?->id,
            'profile' => $profile ? ['id' => $profile->id, 'name' => $profile->name, 'version' => $profile->version] : null,
            'rules' => $this->rulesOf($profile)];
    }

    /** A folded result as one layer, so a level can be measured against everything above it. */
    private function asLayer(array $folded): array
    {
        return ['key' => 'INHERITED', 'rules' => array_map(fn ($r) => ['behavior' => $r['behavior'], 'config' => $r['config']], $folded)];
    }

    private function nodeLayer(string $key, ?ProfileAssignment $assignment): array
    {
        return ['key' => $key, 'rules' => $this->rulesOf($assignment?->profile)];
    }

    private function node(string $type, int $id, string $name, ?ProfileAssignment $assignment, array $inherited): array
    {
        $diffs = $assignment ? $this->diff($this->rulesOf($assignment->profile), $inherited) : [];

        return ['type' => $type, 'id' => $id, 'name' => $name, 'assignment_id' => $assignment?->id,
            'policy' => $assignment?->profile ? ['id' => $assignment->profile->id, 'name' => $assignment->profile->name] : null,
            'diffs' => array_values(array_filter($diffs, fn ($d) => $d['kind'] !== 'same')),
            'repeats' => count(array_filter($diffs, fn ($d) => $d['kind'] === 'same')),
            'children' => []];
    }

    /** One bulk read of every live published assignment, keyed like the engine's lookups. */
    private function active(): Collection
    {
        return $this->active ??= ProfileAssignment::query()->with('profile.capabilities')
            ->whereHas('profile', fn ($q) => $q->where('status', PolicyStatus::PUBLISHED->value))
            ->where('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->orderBy('id')->get();
    }

    private function activeFor(string $layer, int $id): ?ProfileAssignment
    {
        [$scope, $type] = match ($layer) {
            'GLOBAL' => ['GLOBAL', 'System'],
            'COMPANY' => ['COMPANY', 'Tenant'],
            'LOCATION' => ['LOCATION', 'Location'],
            'CATEGORY' => ['NATIVE', 'App\Models\Category'],
            'MODEL' => ['NATIVE', 'App\Models\AssetModel'],
        };

        return $this->active()->first(function ($a) use ($scope, $type, $id, $layer) {
            $level = $a->scope_level instanceof AssignmentScope ? $a->scope_level->value : (string) $a->scope_level;

            return $level === $scope && $a->target_type === $type && (int) $a->target_id === $id
                && (! in_array($layer, ['COMPANY', 'LOCATION'], true) || (int) $a->scope_id === $id);
        });
    }
}
