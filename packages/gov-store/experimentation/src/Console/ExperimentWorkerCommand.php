<?php

namespace GovStore\Experimentation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExperimentWorkerCommand extends Command
{
    protected $signature = 'govstore:experiment-worker {--once} {--stop-when-empty}';

    protected $description = 'Process only GovStore experiment jobs with the appropriate timeout and failure store';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');
        config(['queue.failed.driver' => 'database-uuids', 'queue.failed.table' => 'gov_experiment_failed_jobs']);
        config(['logging.channels.govstore-experiments' => ['driver' => 'daily', 'path' => storage_path('logs/govstore-experiments.log'), 'days' => 14]]);
        Log::setDefaultDriver('govstore-experiments');
        // The fixture worker keeps its short-lived model/permission cache in this process.
        // It must not depend on web-server-owned file cache entries on local Windows installs.
        Cache::setDefaultDriver('array');

        return $this->call('queue:work', ['connection' => config('govstore-experiments.connection'), '--queue' => config('govstore-experiments.queue'),
            '--timeout' => 3600, '--tries' => 1, '--memory' => 512, '--sleep' => 2, '--once' => $this->option('once'), '--stop-when-empty' => $this->option('stop-when-empty')]);
    }
}
