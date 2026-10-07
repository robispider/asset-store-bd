<?php

namespace GovStore\Experimentation\Console;

use App\Models\User;
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\Experimentation\Services\ExperimentAccess;
use GovStore\Experimentation\Services\ExperimentManager;
use GovStore\Experimentation\Services\ExperimentWiper;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class ExperimentCommand extends Command
{
    protected $signature = 'govstore:experiment {action : populate, resume, recover, accounts, preview, wipe, cleanup or status} {--actor= : Super administrator user ID} {--profile=government} {--seed=2026} {--run=} {--scope=dataset} {--confirm=} {--fingerprint=}';

    protected $description = 'Manage isolated Bangladesh government experimentation data';

    public function handle(ExperimentAccess $access, ExperimentManager $manager, ExperimentWiper $wiper): int
    {
        try {
            $actor = User::withoutGlobalScopes()->findOrFail($this->option('actor'));
            $access->authorize($actor);
            if ($this->argument('action') === 'populate') {
                $run = $manager->populate($actor, $this->option('profile'), (int) $this->option('seed'));
                $manager->dispatch($run, $actor);
                $this->info('Dataset: '.$run->id);

                return self::SUCCESS;
            }
            $run = ExperimentRun::findOrFail($this->option('run'));
            switch ($this->argument('action')) {
                case 'accounts': $this->info('Updated fictional accounts: '.$manager->updateAccounts($run, $actor));
                    break;
                case 'recover': $manager->recover($run, $actor);
                    break;
                case 'cleanup': $wiper->cleanupFiles($run, $actor);
                    break;
                case 'resume': $manager->resume($run, $actor);
                    break;
                case 'preview':
                    $preview = $wiper->preview($run, $this->option('scope'), $actor);
                    $this->line(json_encode(array_intersect_key($preview, array_flip(['scope', 'counts', 'blockers', 'confirmation', 'fingerprint'])), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                    return self::SUCCESS;
                case 'wipe': $manager->requestWipe($run, $actor, $this->option('scope'), (string) $this->option('confirm'), (string) $this->option('fingerprint'));
                    break;
                case 'status': break;
                default: throw new \RuntimeException('Unknown action.');
            }
            $run->refresh();
            $this->line($run->id.' '.$run->status.' '.$run->phase);
            if ($run->error) {
                $this->error($run->error);
            }

            return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e instanceof QueryException ? 'Database operation failed: SQLSTATE '.$e->getCode() : $e->getMessage());

            return self::FAILURE;
        }
    }
}
