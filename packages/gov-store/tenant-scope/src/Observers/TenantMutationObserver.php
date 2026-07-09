<?php

namespace GovStore\TenantScope\Observers;

use Illuminate\Database\Eloquent\Model;
use GovStore\TenantScope\Services\TenantBoundaryService;

class TenantMutationObserver
{
    protected TenantBoundaryService $boundaryService;

    public function __construct(TenantBoundaryService $boundaryService)
    {
        $this->boundaryService = $boundaryService;
    }

    public function creating(Model $model)
    {
        $this->boundaryService->verify($model, 'create');
    }

    public function updating(Model $model)
    {
        $this->boundaryService->verify($model, 'update');
    }

    public function deleting(Model $model)
    {
        $this->boundaryService->verify($model, 'delete');
    }
}