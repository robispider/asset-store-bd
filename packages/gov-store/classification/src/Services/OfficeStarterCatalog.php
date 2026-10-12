<?php

namespace GovStore\Classification\Services;

use App\Models\Location;
use GovStore\Classification\Jobs\ExecuteStarterTemplateJob;
use GovStore\Classification\Models\CatalogNode;
use GovStore\Organization\Events\OfficeProvisioned;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Services\OfficeAdministration;
use GovStore\Organization\Services\OfficeLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Durable initial bundle: retries never substitute changed collection contents. */
class OfficeStarterCatalog
{
    public function schedule(OfficeProvisioned $event): void
    {
        if (! $event->location->company_id || ! $event->catalogActorId) {
            return; // Admin assignment publishes the handoff again.
        }
        $runId = null;
        try {
            DB::transaction(function () use ($event, &$runId) {
                $office = Location::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($event->location->id);
                $profile = LocationProfile::where('location_id', $office->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $profile->office_admin_id === $event->catalogActorId
                    && $profile->office_type === $event->officeType
                    && in_array($profile->lifecycle_status, OfficeLifecycleService::ACTIVE, true), 409);
                app(OfficeAdministration::class)->assignee($event->catalogActorId, (int) $office->id, $office->company_id);
                $run = DB::table('gov_office_starter_runs')->where('location_id', $office->id)->lockForUpdate()->first();
                if ($run && $run->status === 'completed') {
                    return;
                }
                if (! $run) {
                    $runId = DB::table('gov_office_starter_runs')->insertGetId([
                        'location_id' => $office->id, 'company_id' => $office->company_id,
                        'actor_id' => $event->catalogActorId, 'office_type' => $event->officeType,
                        'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    $runId = $run->id;
                    abort_unless((int) $run->company_id === (int) $office->company_id && $run->office_type === $profile->office_type, 409);
                }
                // Keep the durable row if preparation fails: it can be retried after library repair.
            });
            if (! $runId) {
                return;
            }
            DB::transaction(function () use ($runId, $event) {
                $run = DB::table('gov_office_starter_runs')->where('id', $runId)->lockForUpdate()->first();
                if ($run->status === 'completed') {
                    return;
                }
                $snapshot = $run->snapshot ? json_decode($run->snapshot, true, 512, JSON_THROW_ON_ERROR) : $this->snapshot($run->office_type);
                DB::table('gov_office_starter_runs')->where('id', $runId)->update([
                    'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'status' => 'pending',
                    'actor_id' => $event->catalogActorId, 'failure_reference' => null, 'updated_at' => now(),
                ]);
                DB::afterCommit(fn () => ExecuteStarterTemplateJob::dispatch(
                    $snapshot, 'location', (int) $run->location_id, $event->catalogActorId, (int) $runId
                ));
            });
        } catch (Throwable $exception) {
            $this->failure($runId, $exception);
        }
    }

    private function snapshot(string $type): array
    {
        $names = config('starter_templates.office_types.'.$type, []);
        if (! $names) {
            throw new RuntimeException('Starter collection configuration is missing.');
        }
        $codes = [];
        foreach ($names as $name) {
            $collections = DB::table('gov_catalog_collections')->where('name', $name)->where('is_active', true)->pluck('id');
            if ($collections->count() !== 1) {
                throw new RuntimeException('Starter collection is missing or ambiguous.');
            }
            $entries = DB::table('gov_catalog_collection_nodes')->where('collection_id', $collections->first())->pluck('code');
            if ($entries->isEmpty()) {
                throw new RuntimeException('Starter collection is empty.');
            }
            foreach ($entries as $code) {
                $node = CatalogNode::where('code', $code)->firstOrFail();
                $leaves = (int) $node->level === 4 ? collect([$node]) : CatalogNode::where('hid', 'like', $node->hid.'%')->where('level', 4)->get();
                if ($leaves->isEmpty()) {
                    throw new RuntimeException('Starter folder has no commodities.');
                }
                foreach ($leaves as $leaf) {
                    if (! $leaf->is_selectable) {
                        throw new RuntimeException('Starter commodity is not selectable.');
                    }
                    $category = DB::table('gov_catalog_snipe_mappings as mapping')->join('categories as category', 'category.id', '=', 'mapping.category_id')
                        ->where('mapping.code', $leaf->code)->whereNull('category.deleted_at')->first(['category.category_type']);
                    $codes[$leaf->code] = ['code' => $leaf->code, 'category_type' => $category?->category_type ?? 'consumable',
                        'fingerprint' => $this->fingerprint($leaf)];
                }
            }
        }
        ksort($codes);

        return array_values($codes);
    }

    private function fingerprint(CatalogNode $node): string
    {
        return hash('sha256', json_encode([$node->scheme, $node->version, $node->code, (int) $node->level,
            $node->title_en, $node->hid, (bool) $node->is_selectable], JSON_THROW_ON_ERROR));
    }

    public function execute(int $runId, array $snapshot, int $actorId, BulkAdoptionService $adoption): void
    {
        try {
            $run = DB::table('gov_office_starter_runs')->where('id', $runId)->first();
            abort_unless($run, 404);
            DB::transaction(function () use ($runId, $run, $snapshot, $actorId, $adoption) {
                $office = Location::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($run->location_id);
                $profile = LocationProfile::where('location_id', $office->id)->lockForUpdate()->firstOrFail();
                $run = DB::table('gov_office_starter_runs')->where('id', $runId)->lockForUpdate()->first();
                if ($run->status === 'completed') {
                    return;
                }
                $context = app(\GovStore\TenantScope\Contexts\TenantContext::class);
                abort_unless($context->locationId === (int) $office->id && $context->companyId === (int) $office->company_id, 404);
                abort_unless((int) $run->actor_id === $actorId && (int) $profile->office_admin_id === $actorId
                    && (int) $run->company_id === (int) $office->company_id && $run->office_type === $profile->office_type
                    && in_array($profile->lifecycle_status, OfficeLifecycleService::ACTIVE, true)
                    && $snapshot === json_decode($run->snapshot, true, 512, JSON_THROW_ON_ERROR), 409);
                app(OfficeAdministration::class)->assignee($actorId, (int) $office->id, $office->company_id);
                foreach ($snapshot as $item) {
                    $node = CatalogNode::where('code', $item['code'])->lockForUpdate()->firstOrFail();
                    if (! hash_equals($item['fingerprint'], $this->fingerprint($node))) {
                        throw new RuntimeException('The frozen starter reference bundle has changed.');
                    }
                    $mapping = DB::table('gov_catalog_snipe_mappings')->where('code', $item['code'])->first();
                    if ($mapping) {
                        $category = DB::table('categories')->where('id', $mapping->category_id)->whereNull('deleted_at')->first();
                        if (! $category || $category->category_type !== $item['category_type']) {
                            throw new RuntimeException('Starter category mapping is unavailable or has changed type.');
                        }
                    }
                }
                $adoption->execute($snapshot, 'location', (int) $office->id, $actorId);
                DB::table('gov_office_starter_runs')->where('id', $runId)->update([
                    'status' => 'completed', 'completed_at' => now(), 'failure_reference' => null, 'updated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->failure($runId, $exception);
            throw $exception; // Queue retries retain the same frozen payload.
        }
    }

    public function failure(?int $runId, Throwable $exception): void
    {
        $reference = (string) Str::uuid();
        Log::error('Office starter catalog failed', ['reference_id' => $reference, 'run_id' => $runId, 'exception' => $exception]);
        if ($runId) {
            DB::table('gov_office_starter_runs')->where('id', $runId)->where('status', '!=', 'completed')->update([
                'status' => 'failed', 'failure_reference' => $reference, 'updated_at' => now(),
            ]);
        }
    }
}
