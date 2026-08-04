<?php

namespace GovStore\Classification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GovStore\Classification\Services\BulkAdoptionService;

class ExecuteStarterTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $codes;
    protected string $scopeType;
    protected int $scopeId;
    protected int $userId;

    public function __construct(array $codes, string $scopeType, int $scopeId, int $userId)
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
        // Execute the exact same engine used by the UI Modal, but silently in the background
        $adoptionService->execute(
            $this->codes, 
            $this->scopeType, 
            $this->scopeId, 
            $this->userId
        );
    }
}