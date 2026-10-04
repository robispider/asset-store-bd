<?php

namespace GovStore\Experimentation\Jobs;

use App\Models\User;
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\Experimentation\Services\BangladeshScenario;
use GovStore\Experimentation\Services\ExperimentAccess;
use GovStore\Experimentation\Services\ExperimentManager;
use GovStore\Experimentation\Services\ExperimentVerifier;
use GovStore\Experimentation\Services\ExperimentWiper;
use GovStore\Experimentation\Services\InstallationLock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

class ExperimentJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public bool $failOnTimeout = true;

    public function __construct(public string $runId, public int $actorId, public string $operation,
        public string $scope, public ?string $fingerprint, public string $installation) {}

    public function displayName(): string
    {
        return self::class.':'.$this->runId;
    }

    public function handle(ExperimentAccess $access, InstallationLock $lock, ExperimentManager $manager,
        BangladeshScenario $scenario, ExperimentVerifier $verifier, ExperimentWiper $wiper): void
    {
        $lock->run(function () use ($access, $manager, $scenario, $verifier, $wiper) {
            $run = ExperimentRun::findOrFail($this->runId);
            if (! in_array($this->operation, ['populate', 'wipe'], true)) {
                throw new RuntimeException('Invalid experiment operation.');
            }
            if ($run->status !== ($this->operation === 'wipe' ? 'wipe_pending' : 'pending')) {
                return;
            }
            try {
                $actor = User::withoutGlobalScopes()->findOrFail($this->actorId);
                $access->authorize($actor);
                if (! $actor->activated || $actor->deleted_at) {
                    throw new RuntimeException('The initiating super administrator is inactive.');
                }
                if (! hash_equals($manager->installationIdentity(), $this->installation)) {
                    throw new RuntimeException('Queued operation belongs to another database installation.');
                }
                $run->update(['status' => $this->operation === 'wipe' ? 'wiping' : 'running', 'error' => null]);
                if ($this->operation === 'wipe') {
                    $wiper->wipe($run, $this->scope, $actor, $this->fingerprint ?? '');
                } else {
                    $verifier->preflight();
                    $scenario->populate($run, $actor, $run->password);
                    $verification = $verifier->verify($run, $actor);
                    $report = $run->refresh()->report;
                    $report['verification'] = $verification;
                    $run->update(['status' => $verification['passed'] ? 'ready' : 'partial', 'phase' => 'verified', 'report' => $report]);
                }
            } catch (\Throwable $e) {
                // Keep a useful diagnostic, but never credentials or raw SQL bindings.
                $message = $e instanceof QueryException ? get_class($e).' SQLSTATE '.$e->getCode() : $e->getMessage();
                $run->update(['status' => 'failed', 'error' => mb_substr(str_replace($run->password, '[redacted]', $message), 0, 2000)]);
                throw $e;
            }
        });
    }

    public function failed(?\Throwable $exception): void
    {
        ExperimentRun::where('id', $this->runId)->whereIn('status', ['pending', 'running', 'wipe_pending', 'wiping'])
            ->update(['status' => 'failed', 'error' => 'The worker stopped. Inspect its log before resuming this dataset.']);
    }
}
