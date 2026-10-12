<?php

namespace GovStore\Tracking\Console\Commands;

use GovStore\Tracking\Jobs\RebuildTrackingProjectionJob;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Repositories\EloquentTrackingProjectionRepository;
use Illuminate\Console\Command;

class TrackingRebuildProjectionsCommand extends Command
{
    protected $signature = 'govstore:rebuild-projections {initiative_id? : Initiative whose lifecycle cache should be rebuilt}';
    protected $description = 'Refresh lifecycle summary caches from active associations; preserve historical delivery facts';

    public function handle(): int
    {
        $query = Initiative::withoutGlobalScopes();
        if ($id = $this->argument('initiative_id')) $query->whereKey($id);
        if (! $query->exists()) {
            $this->warn('No matching initiatives.');
            return $id ? self::FAILURE : self::SUCCESS;
        }
        $query->orderBy('id')->chunkById(100, function ($initiatives) {
            foreach ($initiatives as $initiative) {
                (new RebuildTrackingProjectionJob((int) $initiative->id))->handle(app(EloquentTrackingProjectionRepository::class));
                $this->line('Refreshed initiative '.$initiative->id);
            }
        });
        return self::SUCCESS;
    }
}
