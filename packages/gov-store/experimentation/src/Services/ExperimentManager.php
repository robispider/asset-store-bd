<?php

namespace GovStore\Experimentation\Services;

use App\Models\User;
use GovStore\Experimentation\Jobs\ExperimentJob;
use GovStore\Experimentation\Models\ExperimentRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ExperimentManager
{
    public function __construct(private ExperimentAccess $access, private InstallationLock $lock, private ExperimentWiper $wiper) {}

    public function populate(User $actor, string $profile, int $seed = 2026, ?string $label = null): ExperimentRun
    {
        $this->access->authorize($actor);
        if (! array_key_exists($profile, config('govstore-experiments.profiles')) || $seed < 0 || $seed > 2147483647) {
            throw new RuntimeException('Invalid experiment profile or seed.');
        }

        return $this->lock->run(function () use ($actor, $profile, $seed, $label) {
            $this->idle();
            $run = ExperimentRun::create(['label' => $label ?: 'Bangladesh government experiment '.now()->format('Y-m-d H:i:s'),
                'profile' => $profile, 'seed' => $seed, 'initiated_by' => $actor->id, 'anchor_date' => now(),
                'password' => BangladeshPeople::PASSWORD, 'status' => 'pending', 'report' => ['scenario_version' => 'bd-government-v1', 'account_format' => 'english-name-number-v1', 'record_prefix' => 'EXP-'.Str::random(16), 'profile_counts' => config('govstore-experiments.profiles.'.$profile)]]);
            $this->audit($run, $actor, 'populate');

            // Dispatch after releasing the admission lock (sync queues acquire the worker lock).
            return $run;
        });
    }

    public function dispatch(ExperimentRun $run, User $actor, string $operation = 'populate', string $scope = 'dataset', ?string $fingerprint = null): void
    {
        $this->access->authorize($actor);
        try {
            ExperimentJob::dispatch($run->id, $actor->id, $operation, $scope, $fingerprint, $this->installationIdentity())
                ->onConnection(config('govstore-experiments.connection'))->onQueue(config('govstore-experiments.queue'));
        } catch (\Throwable $e) {
            if ($run->refresh()->status !== 'failed') {
                $run->update(['status' => 'failed', 'error' => get_class($e).' (dispatch failed)']);
            }
            throw $e;
        }
    }

    public function resume(ExperimentRun $run, User $actor): void
    {
        $this->access->authorize($actor);
        $this->lock->run(function () use ($run, $actor) {
            $this->idle();
            $run->refresh();
            if ($run->status !== 'failed' || $run->wiped_at || $run->phase === 'wipe') {
                throw new RuntimeException('Only failed populations can be resumed.');
            }
            $run->update(['status' => 'pending', 'error' => null]);
            $this->audit($run, $actor, 'resume');
        });
        $this->dispatch($run, $actor);
    }

    public function requestWipe(ExperimentRun $run, User $actor, string $scope, string $confirmation, string $fingerprint): void
    {
        $this->access->authorize($actor);
        $this->lock->run(function () use ($run, $actor, $scope, $confirmation, $fingerprint) {
            $this->idle();
            $run->refresh();
            if ($run->wiped_at && $scope === 'dataset') {
                throw new RuntimeException('This dataset has already been wiped.');
            }
            $preview = $this->wiper->preview($run, $scope, $actor);
            if ($preview['blockers'] || ! hash_equals($preview['fingerprint'], $fingerprint)
                || ! hash_equals($preview['confirmation'], $confirmation)) {
                throw new RuntimeException(__('experiments::ui.changed_preview'));
            }
            $run->update(['status' => 'wipe_pending', 'phase' => 'wipe']);
            $this->audit($run, $actor, 'wipe_requested', $scope);
        });
        $this->dispatch($run, $actor, 'wipe', $scope, $fingerprint);
    }

    public function installationIdentity(): string
    {
        $connection = DB::connection();

        return hash('sha256', json_encode([$connection->getDriverName(), $connection->getConfig('host'), $connection->getConfig('port'), $connection->getDatabaseName(), config('govstore-experiments.installation')]));
    }

    public function updateAccounts(ExperimentRun $run, User $actor): int
    {
        $this->access->authorize($actor);

        return $this->lock->run(function () use ($run, $actor) {
            $this->idle();

            return DB::transaction(function () use ($run, $actor) {
                $run->refresh();
                if ($run->wiped_at || ! in_array($run->status, ['ready', 'failed'], true)) {
                    throw new RuntimeException('Only existing inactive datasets can update their accounts.');
                }
                $ids = app(RecordRegistry::class)->ids($run, 'users');
                $people = User::withoutGlobalScopes()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                foreach ($people as $person) {
                    app(ExperimentAccounts::class)->update($person);
                }
                $report = $run->report;
                $report['account_format'] = 'english-name-number-v1';
                $run->update(['password' => BangladeshPeople::PASSWORD, 'report' => $report]);
                $this->audit($run, $actor, 'update_accounts');

                return $people->count();
            });
        });
    }

    public function recover(ExperimentRun $run, User $actor): void
    {
        $this->access->authorize($actor);
        $this->lock->run(function () use ($run, $actor) {
            $run->refresh();
            if (! in_array($run->status, ['pending', 'running', 'wipe_pending', 'wiping'], true)) {
                throw new RuntimeException('Only a stopped or pending operation can be released.');
            }
            // Acquiring the same lock proves no worker is currently changing fixture data.
            foreach (DB::table('gov_experiment_jobs')->where('queue', config('govstore-experiments.queue'))->get() as $job) {
                $payload = json_decode($job->payload, true);
                if (($payload['displayName'] ?? null) === ExperimentJob::class.':'.$run->id) {
                    DB::table('gov_experiment_jobs')->where('id', $job->id)->delete();
                }
            }
            $run->update(['status' => 'failed', 'error' => 'The pending or stopped operation was released. Resume population or review a new wipe preview.']);
            $this->audit($run, $actor, 'recover');
        });
    }

    private function idle(): void
    {
        if (ExperimentRun::whereIn('status', ['pending', 'running', 'wipe_pending', 'wiping'])->exists()) {
            throw new RuntimeException(__('experiments::ui.busy'));
        }
    }

    private function audit(ExperimentRun $run, User $actor, string $action, string $scope = 'dataset'): void
    {
        DB::table('gov_experiment_actions')->insert(['run_id' => $run->id, 'actor_id' => $actor->id, 'action' => $action, 'scope' => $scope, 'created_at' => now(), 'updated_at' => now()]);
    }
}
