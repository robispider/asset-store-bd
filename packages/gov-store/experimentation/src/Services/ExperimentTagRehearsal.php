<?php

namespace GovStore\Experimentation\Services;

use App\Models\AssetModel;
use App\Models\Location;
use App\Models\User;
use GovStore\AssetTagging\Http\Controllers\EnablementController;
use GovStore\AssetTagging\Services\EnablementGate;
use GovStore\Classification\Models\FinanceItem;
use GovStore\Classification\Services\ProductTypeFinanceDefault;
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\StoreOperations\Services\DocumentValidationService;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use GovStore\StoreOperations\Services\PostingPipelineManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit, permanent issuance exercise; never changes installation gates or Finance masters. */
class ExperimentTagRehearsal
{
    public function run(ExperimentRun $run, User $actor): array
    {
        app(ExperimentAccess::class)->authorize($actor);

        return app(InstallationLock::class)->run(function () use ($run, $actor) {
            $run->refresh();
            if (! ScenarioManifest::current($run) || $run->status !== 'ready' || $run->wiped_at
                || ExperimentRun::whereIn('status', ['pending', 'running', 'wipe_pending', 'wiping'])->exists()) {
                throw new RuntimeException('Tag rehearsal requires an idle current ready dataset.');
            }
            if (isset($run->report['tag_rehearsal'])) {
                return $run->report['tag_rehearsal'];
            }
            $records = app(RecordRegistry::class);
            $office = Location::withoutGlobalScopes()->findOrFail($records->ids($run, 'locations')[0]);
            $gate = app(EnablementGate::class);
            if (! $gate->rehearsal() || ! $gate->passes($office->id)) {
                throw new RuntimeException('The asset-tagging nonproduction rehearsal and all installation/office gates must already pass. No gate was changed.');
            }
            $model = AssetModel::withoutGlobalScopes()->whereIn('id', $records->ids($run, 'models'))
                ->where('name', 'like', '%Desktop Computers%')->orderBy('id')->firstOrFail();
            $defaults = app(ProductTypeFinanceDefault::class);
            $finance = FinanceItem::where('status', 'active')->orderBy('gov_code')->get()
                ->first(fn ($item) => $defaults->rejection($item->gov_code, $model->category_id) === null);
            if (! $finance) {
                throw new RuntimeException('A reviewed active compatible Finance item is required. No Finance master was created or activated.');
            }
            $previous = $records->current;
            $records->current = $run;
            try {
                $result = app(ActorContext::class)->run($actor, $office, fn () => DB::transaction(function () use ($run, $actor, $office, $model, $finance, $records) {
                    $controller = app(EnablementController::class);
                    $request = Request::create('/', 'POST', ['reason' => 'Explicit fictional managed tag rehearsal']);
                    $controller->enable($request, $office->id);
                    $documents = [];
                    try {
                        foreach (['Purchase', 'Donation'] as $source) {
                            $document = app(GoodsReceiptService::class)->saveDraft(['purchase_type' => $source,
                                'supplier_id' => $records->ids($run, 'suppliers')[0]],
                                [['type' => 'assetmodel', 'id' => $model->id, 'qty' => 1, 'unit_cost' => 65000]], $actor->id);
                            $document->references()->create(['reference_type' => 'Supplier Challan', 'reference_number' => 'Fictional tag rehearsal', 'reference_date' => now()->toDateString()]);
                            $item = $document->items()->firstOrFail();
                            foreach (['serial_number' => $run->report['display_id'].'-TAG-'.$source, 'gov_code' => $finance->gov_code, 'warranty_months' => '36'] as $field => $value) {
                                $item->metadata()->create(['field_key' => $field, 'row_index' => 0, 'value' => $value]);
                            }
                            $validation = app(DocumentValidationService::class);
                            if ($validation->validateDocument($document->fresh(), []) || ! $validation->evaluateDocument($document->fresh())['is_valid']) {
                                throw new RuntimeException('Managed tag receipt failed its posting checklist.');
                            }
                            app(PostingPipelineManager::class)->materialize($document->fresh(), $actor->id);
                            foreach (DB::table('gov_asset_registrations')->where('intake_item_id', $item->id)->get() as $row) {
                                $records->record($run, 'gov_asset_registrations', $row);
                            }
                            $documents[] = $document->id;
                        }
                    } finally {
                        $controller->disable(Request::create('/', 'POST', ['reason' => 'Fictional rehearsal complete; future issuance disabled']), $office->id);
                    }
                    $result = ['document_ids' => $documents, 'office_id' => $office->id, 'issued_assets' => 2, 'completed_at' => now()->toIso8601String()];
                    $report = $run->fresh()->report;
                    $report['tag_rehearsal'] = $result;
                    $run->update(['report' => $report]);
                    DB::table('gov_experiment_actions')->insert(['run_id' => $run->id, 'actor_id' => $actor->id,
                        'action' => 'tag_rehearsal', 'scope' => 'dataset', 'created_at' => now(), 'updated_at' => now()]);

                    return $result;
                }));
                $verification = app(ExperimentVerifier::class)->verify($run->fresh(), $actor);
                $report = $run->fresh()->report;
                $report['verification'] = $verification;
                $run->update(['report' => $report, 'status' => $verification['passed'] ? 'ready' : 'partial']);

                return $result;
            } finally {
                $records->current = $previous;
            }
        });
    }
}
