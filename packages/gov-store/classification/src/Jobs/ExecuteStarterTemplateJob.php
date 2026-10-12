<?php

namespace GovStore\Classification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GovStore\Classification\Services\BulkAdoptionService;

class ExecuteStarterTemplateJob implements ShouldQueue, \GovStore\TenantScope\Contracts\TenantScopedExecution
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $codes;
    protected string $scopeType;
    protected int $scopeId;
    protected int $userId;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(array $codes, string $scopeType, int $scopeId, int $userId, private ?int $runId = null)
    {
        $this->codes = $codes;
        $this->scopeType = $scopeType;
        $this->scopeId = $scopeId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(BulkAdoptionService $adoptionService)
    {
        if ($this->runId) {
            // Also guard direct service invocation; bus middleware protects normal delivery.
            return app(\GovStore\TenantScope\Services\TenantExecution::class)->run($this->tenantExecution(),
                fn () => app(\GovStore\Classification\Services\OfficeStarterCatalog::class)->execute(
                    $this->runId, $this->codes, $this->userId, $adoptionService
                ));
        }
        // Execute the exact same engine used by the UI Modal, but silently in the background
        $adoptionService->execute(
            $this->codes, 
            $this->scopeType, 
            $this->scopeId, 
            $this->userId
        );
    }

    public function failed(?\Throwable $exception): void
    {
        if ($exception && $this->runId) {
            app(\GovStore\Classification\Services\OfficeStarterCatalog::class)->failure($this->runId, $exception);
        }
    }

    public function tenantExecution(): array
    {
        return ['actor_id' => $this->userId, 'scope_type' => $this->scopeType,
            'scope_id' => $this->scopeId, 'ability' => 'catalog.office.adopt'];
    }
}
