<?php

namespace GovStore\Metadata\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AssetModel;
use GovStore\Metadata\Services\ConvergenceEngine;

class ConvergeMetadataJob implements ShouldQueue, \GovStore\TenantScope\Contracts\GlobalTenantMaintenance
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    public function __construct(public int $operationId) {}

    /**
     * Executes the background convergence loop.
     */
    public function handle(ConvergenceEngine $engine): void
    {
        $operation = \Illuminate\Support\Facades\DB::transaction(function () {
            $row = \Illuminate\Support\Facades\DB::table('gov_reviewed_operations')->where('id', $this->operationId)->lockForUpdate()->first();
            abort_unless($row && $row->status === 'queued' && $row->ability === 'metadata.national.manage'
                && \Illuminate\Support\Facades\DB::table('gov_access_review_uses')->where('token_hash', $row->review_hash)->exists(), 403);
            $actor = \App\Models\User::withoutGlobalScopes()->whereNull('deleted_at')->where('activated', true)->find($row->actor_id);
            abort_unless($actor && $actor->isSuperUser(), 403);
            $plan = app(\GovStore\Metadata\Services\MetadataMaintenancePlan::class)->snapshot();
            abort_unless(hash_equals($row->plan_hash, hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR))), 409);
            $row->model_ids = array_column($plan['models'], 'id');
            \Illuminate\Support\Facades\DB::table('gov_reviewed_operations')->where('id', $row->id)->update(['status' => 'running', 'updated_at' => now()]);

            return $row;
        });
        $processed = 0;
        $failed = 0;
        $reference = (string) \Illuminate\Support\Str::uuid();
        // Fetch all models globally, bypassing tenant scope constraints
        $models = AssetModel::withoutGlobalScopes()->whereIn('id', $operation->model_ids)->whereNull('deleted_at')->get();
        $failed = count($operation->model_ids) - $models->count();

        foreach ($models as $model) {
            try {
                $engine->converge($model);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                // Log failure for individual models but continue processing the rest of the queue
                logger()->error('Metadata convergence failed', ['reference_id' => $reference, 'model_id' => $model->id, 'operation_id' => $operation->id, 'exception' => $e]);
            }
        }
        \Illuminate\Support\Facades\DB::table('gov_reviewed_operations')->where('id', $operation->id)->update([
            'status' => $failed ? 'failed' : 'completed', 'processed' => $processed, 'failed' => $failed,
            'reference_id' => $reference, 'finished_at' => now(), 'updated_at' => now(),
        ]);
        logger()->info('Reviewed metadata convergence finished', ['operation_id' => $operation->id, 'actor_id' => $operation->actor_id,
            'processed' => $processed, 'failed' => $failed, 'reference_id' => $reference]);
    }

    public function failed(?\Throwable $exception): void
    {
        $reference = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('gov_reviewed_operations')->where('id', $this->operationId)
            ->whereIn('status', ['queued', 'running'])->update(['status' => 'failed', 'reference_id' => $reference, 'finished_at' => now(), 'updated_at' => now()]);
        logger()->error('Reviewed metadata operation stopped', ['operation_id' => $this->operationId, 'reference_id' => $reference, 'exception' => $exception]);
    }
}
