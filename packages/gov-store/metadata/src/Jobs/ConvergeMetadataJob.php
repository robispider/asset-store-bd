<?php

namespace GovStore\Metadata\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AssetModel;
use GovStore\Metadata\Services\ConvergenceEngine;

class ConvergeMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Executes the background convergence loop.
     */
    public function handle(ConvergenceEngine $engine): void
    {
        // Fetch all models globally, bypassing tenant scope constraints
        $models = AssetModel::withoutGlobalScopes()->get();

        foreach ($models as $model) {
            try {
                $engine->converge($model);
            } catch (\Exception $e) {
                // Log failure for individual models but continue processing the rest of the queue
                logger()->error("Queue Convergence failed for Model ID {$model->id}: " . $e->getMessage());
            }
        }
    }
}