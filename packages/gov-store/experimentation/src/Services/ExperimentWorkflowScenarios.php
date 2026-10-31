<?php

namespace GovStore\Experimentation\Services;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use GovStore\CustomRequests\Models\ApprovalPolicy;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Services\ApprovalService;
use GovStore\CustomRequests\Services\BasketService;
use GovStore\CustomRequests\Services\FulfillmentService;
use GovStore\CustomRequests\Services\RequesterService;
use GovStore\CustomRequests\Services\RequestReturnService;
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Each unit commits its real actions and its checkpoint together. No final-state fixtures. */
class ExperimentWorkflowScenarios
{
    public function __construct(private ActorContext $actors, private RecordRegistry $records) {}

    public function populate(ExperimentRun $run, User $root, array $offices, array $people, callable $unit, callable $post): void
    {
        $operational = array_values(array_filter($offices, fn ($office) => DB::table('gov_office_responsibilities')
            ->where('location_id', $office->id)->where('role_slug', 'storekeeper')->exists()));
        foreach (['asset_model', 'consumable', 'accessory'] as $type) {
            foreach (ScenarioManifest::REQUEST_RECIPES as $index => $recipe) {
                $office = $operational[$index % count($operational)];
                $unit('request-'.$type.'-'.$recipe, fn () => $this->request($run, $office, $type, $recipe));
            }
            if ($type !== 'asset_model') {
                $unit('request-'.$type.'-return', fn () => $this->returned($run, $operational[0], $type, $post));
                $unit('request-'.$type.'-substitution', fn () => $this->substituted($run, $operational[0], $type));
            }
        }
        $unit('mixed-policy', fn () => $this->mixed($run, $operational[0]));
        $unit('stock-adjustments', fn () => $this->adjustments($run, $operational[0], $post));
        app(ExperimentPackageScenarios::class)->populate($run, $root, $offices, $people, $unit, $post);
    }

    private function members(Location $office): array
    {
        $profile = DB::table('gov_location_profiles')->where('location_id', $office->id)->first();
        $actors = ['office_admin' => User::withoutGlobalScopes()->findOrFail($profile->office_admin_id)];
        foreach (['storekeeper', 'primary_approver', 'final_approver'] as $role) {
            $id = DB::table('gov_office_responsibilities')->where('location_id', $office->id)->where('role_slug', $role)->value('user_id');
            $actors[$role] = User::withoutGlobalScopes()->findOrFail($id);
        }
        $actors['employee'] = User::withoutGlobalScopes()->where('location_id', $office->id)
            ->whereNotIn('id', array_map(fn ($u) => $u->id, $actors))->orderBy('id')->firstOrFail();

        return $actors;
    }

    private function item(ExperimentRun $run, Location $office, string $type)
    {
        if ($type === 'asset_model') {
            $modelId = Asset::withoutGlobalScopes()->whereIn('id', $this->records->ids($run, 'assets'))
                ->where('location_id', $office->id)->whereHas('model', fn ($q) => $q->where('name', 'like', '%Printers%'))->value('model_id');

            return AssetModel::withoutGlobalScopes()->findOrFail($modelId);
        }
        $class = $type === 'consumable' ? Consumable::class : Accessory::class;

        return $class::withoutGlobalScopes()->whereIn('id', $this->records->ids($run, $class === Consumable::class ? 'consumables' : 'accessories'))
            ->where('location_id', $office->id)->orderBy('id')->firstOrFail();
    }

    private function policy(string $type, int $id, string $recipe): void
    {
        ApprovalPolicy::updateOrCreate(['target_type' => $type, 'target_id' => $id], [
            'policy_name' => $recipe === 'auto' ? 'AUTO_APPROVE' : ($recipe === 'two_stage' ? 'PRIMARY_AND_FINAL' : 'PRIMARY_ONLY'),
            'threshold_qty' => $recipe === 'threshold' ? 2 : null, 'threshold_value' => null,
        ]);
    }

    private function submit(Location $office, User $employee, string $type, int $id, string $recipe): ServiceRequest
    {
        $this->policy($type, $id, $recipe);

        return $this->actors->run($employee, $office, function () use ($office, $employee, $type, $id, $recipe) {
            $basket = app(BasketService::class);
            $basket->addItem($employee->id, $type, $id, 2);
            $draft = $basket->getOrCreateDraftBasket($employee->id);

            return $basket->submitBasket($employee->id, ['request_type' => match ($recipe) {
                'threshold' => 'project', 'reduced' => 'repair', 'complete' => 'office_setup', 'partial' => 'new_employee',
                'acknowledged' => 'replacement', 'closed' => 'emergency', default => 'other',
            }, 'purpose' => 'Fictional '.$recipe.' scenario', 'justification' => 'Fictional official-use demonstration',
                'delivery_location_id' => $office->id, 'declared' => true], $basket->snapshot($draft))[0];
        });
    }

    private function approve(Location $office, array $actors, ServiceRequest $request, string $recipe): ServiceRequest
    {
        if ($request->approval_status === 'approved') {
            return $request;
        }
        foreach (['primary_approver', 'final_approver'] as $role) {
            if (! in_array($request->approval_status, ['pending_primary', 'pending_final'], true)) {
                break;
            }
            $request = $this->actors->run($actors[$role], $office, fn () => app(ApprovalService::class)->processDecision(
                $request->fresh(), $actors[$role], $request->items()->get()->mapWithKeys(fn ($line) => [$line->id => [
                    'status' => $recipe === 'rejected' ? 'rejected' : 'approved', 'qty' => $recipe === 'reduced' ? 1 : 2,
                    'notes' => 'Fictional '.$recipe.' decision',
                ]])->all()), $role);
        }

        return $request;
    }

    private function issue(Location $office, array $actors, ServiceRequest $request, string $type, int $quantity, ?int $alternative = null): ServiceRequest
    {
        return $this->actors->run($actors['storekeeper'], $office, function () use ($office, $actors, $request, $type, $quantity, $alternative) {
            $line = $request->items()->firstOrFail();
            $payload = $quantity;
            if ($type === 'asset_model') {
                $payload = Asset::where('location_id', $office->id)->where('model_id', $line->requested_id)
                    ->whereNull('assigned_to')->where('requestable', 1)->whereHas('status', fn ($q) => $q->where('deployable', 1)->where('archived', 0))
                    ->orderBy('id')->limit($quantity)->pluck('id')->all();
                if (count($payload) !== $quantity) {
                    throw new RuntimeException('Not enough fictional serials for a required issue recipe.');
                }
            }
            $service = app(FulfillmentService::class);

            return $service->issueItems($request->fresh(), $actors['storekeeper'], [$line->id => $payload],
                $alternative ? [$line->id => $alternative] : [], 'Fictional supply', null, $service->issueVersion($request->fresh()));
        }, 'storekeeper');
    }

    private function request(ExperimentRun $run, Location $office, string $type, string $recipe): array
    {
        $actors = $this->members($office);
        $request = $this->submit($office, $actors['employee'], $type, $this->item($run, $office, $type)->id, $recipe);
        if ($recipe === 'withdrawn') {
            $request = $this->actors->run($actors['employee'], $office, fn () => app(RequesterService::class)->transition($request->id, $actors['employee'], 'withdraw'));
        } elseif ($recipe !== 'pending') {
            $request = $this->approve($office, $actors, $request, $recipe);
        }
        $issued = 0;
        if (in_array($recipe, ['partial', 'complete', 'acknowledged', 'closed'], true)) {
            $issued = in_array($recipe, ['partial', 'closed'], true) ? 1 : 2;
            if ($recipe === 'complete') {
                $request = $this->issue($office, $actors, $request, $type, 1);
                $request = $this->issue($office, $actors, $request, $type, 1);
            } else {
                $request = $this->issue($office, $actors, $request, $type, $issued);
            }
            if ($recipe === 'acknowledged') {
                $request = $this->actors->run($actors['employee'], $office, fn () => app(RequesterService::class)->transition($request->id, $actors['employee'], 'receive'));
            } elseif ($recipe === 'closed') {
                $request = $this->actors->run($actors['storekeeper'], $office, fn () => app(FulfillmentService::class)->forceClose(
                    $request->fresh(), $actors['storekeeper'], 'Fictional remainder no longer needed', 'not_needed'), 'storekeeper');
            }
        }

        return ['request_id' => $request->id, 'type' => $type, 'recipe' => $recipe, 'issued' => $issued];
    }

    private function returned(ExperimentRun $run, Location $office, string $type, callable $post): array
    {
        $actors = $this->members($office);
        $request = $this->submit($office, $actors['employee'], $type, $this->item($run, $office, $type)->id, 'primary');
        $request = $this->approve($office, $actors, $request, 'primary');
        $request = $this->issue($office, $actors, $request, $type, 2);
        $this->actors->run($actors['employee'], $office, fn () => app(RequestReturnService::class)->requestReturn($request->id, $actors['employee'], 'Fictional unused supplies returned'));
        $document = $this->actors->run($actors['storekeeper'], $office, function () use ($request, $actors, $post) {
            $document = app(RequestReturnService::class)->draftReceipt($request->id, $actors['storekeeper']);
            $document->references()->create(['reference_type' => 'Nothi / Approval Letter', 'reference_number' => $request->request_number]);
            $post($document, $actors['storekeeper']);

            return $document;
        }, 'storekeeper');

        return ['request_id' => $request->id, 'type' => $type, 'recipe' => 'return', 'issued' => 2, 'document_id' => $document->id];
    }

    private function substituted(ExperimentRun $run, Location $office, string $type): array
    {
        $actors = $this->members($office);
        $item = $this->item($run, $office, $type);
        $alternative = $item->newQueryWithoutScopes()->whereIn('id', $this->records->ids($run, $item->getTable()))
            ->where('location_id', $office->id)->where('category_id', $item->category_id)->where('id', '!=', $item->id)->firstOrFail();
        $request = $this->submit($office, $actors['employee'], $type, $item->id, 'primary');
        $request = $this->approve($office, $actors, $request, 'primary');
        $request = $this->issue($office, $actors, $request, $type, 2, $alternative->id);

        return ['request_id' => $request->id, 'type' => $type, 'recipe' => 'substitution', 'issued' => 2, 'alternative_id' => $alternative->id];
    }

    private function mixed(ExperimentRun $run, Location $office): array
    {
        $actors = $this->members($office);
        $employee = $actors['employee'];

        return $this->actors->run($employee, $office, function () use ($run, $office, $employee) {
            $basket = app(BasketService::class);
            foreach (['consumable' => 'primary', 'accessory' => 'two_stage'] as $type => $policy) {
                $item = $this->item($run, $office, $type);
                $this->policy($type, $item->id, $policy);
                $basket->addItem($employee->id, $type, $item->id, 1);
            }
            $submitted = $basket->submitBasket($employee->id, ['request_type' => 'project', 'purpose' => 'Fictional mixed-policy submission',
                'justification' => 'Fictional office project', 'delivery_location_id' => $office->id, 'declared' => true]);

            return ['request_ids' => array_map(fn ($r) => $r->id, $submitted)];
        });
    }

    private function adjustments(ExperimentRun $run, Location $office, callable $post): array
    {
        $actors = $this->members($office);

        return $this->actors->run($actors['storekeeper'], $office, function () use ($run, $office, $actors, $post) {
            $source = Document::whereIn('id', $this->records->ids($run, 'gov_documents'))->where('location_id', $office->id)
                ->where('type', 'receipt')->where('status', 'POSTED')->firstOrFail();
            $item = $this->item($run, $office, 'consumable');
            $ids = [];
            foreach (['IN', 'OUT'] as $direction) {
                $document = app(GoodsReceiptService::class)->saveDraft(['source_document_id' => $source->id, 'adjustment_reason' => 'PHYSICAL_COUNT'],
                    [['type' => 'consumable', 'id' => $item->id, 'qty' => 1, 'unit_cost' => 250]], $actors['storekeeper']->id, null, 'adjustment');
                $document->items()->firstOrFail()->metadata()->create(['field_key' => 'adjustment_direction', 'row_index' => 0, 'value' => $direction]);
                $post($document, $actors['storekeeper']);
                $ids[] = $document->id;
            }

            return ['document_ids' => $ids];
        }, 'storekeeper');
    }
}
