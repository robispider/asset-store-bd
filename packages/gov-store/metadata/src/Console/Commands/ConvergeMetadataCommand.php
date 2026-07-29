<?php

namespace GovStore\Metadata\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AssetModel;
use GovStore\Metadata\Services\ConvergenceEngine;

class ConvergeMetadataCommand extends Command
{
    protected $signature = 'govstore:metadata-converge';
    protected $description = 'Reconciles out-of-sync database model schemas with active platform definitions';

    public function handle(ConvergenceEngine $engine): int
    {
        $this->info('Starting GovStore Metadata Convergence Engine...');

        // Query all models, bypassing scope parameters
        $models = AssetModel::withoutGlobalScopes()->get();

        if ($models->isEmpty()) {
            $this->warn('No Asset Models found.');
            return 0;
        }

        $convergedCount = 0;
        $compliantCount = 0;

        foreach ($models as $model) {
            $this->line("Evaluating Model: [ID: {$model->id}] '{$model->name}'...");

            try {
                $converged = $engine->converge($model);
                if ($converged) {
                    $this->info("--> CONVERGED: Updated physical field configurations.");
                    $convergedCount++;
                } else {
                    $this->line("--> COMPLIANT: Schema matches deployment blueprint.");
                    $compliantCount++;
                }
            } catch (\Exception $e) {
                $this->error("--> FAILURE: Could not converge model ID {$model->id}. Error: " . $e->getMessage());
            }
        }

        $this->info("Convergence complete. (Compliant: {$compliantCount}, Converged: {$convergedCount})");
        return 0;
    }
}