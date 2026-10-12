<?php

namespace GovStore\Tracking\Services;

use GovStore\Tracking\Models\TrackingCode;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Validation\ValidationException;

class ProgrammeVerifier
{
    public function __construct(private ScopeValidatorService $scopeValidator, private TenantContext $context) {}

    public function authorizeOffice(int $locationId): void
    {
        $user = auth()->user();
        abort_unless($user && $this->context->isActive && $this->context->canUseInventoryOffice()
            && $this->context->locationId === $locationId, 403);
        abort_unless(app(GovAccess::class)->permitsRequest($user, 'storeops.documents.view'), 403);
    }

    public function resolve(string $code, int $locationId): TrackingCode
    {
        $this->authorizeOffice($locationId);
        // Execution participation can legitimately cross the initiative management scope.
        $task = TrackingCode::with(['initiative' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('tracking_code', $code)->where('status', 'ACTIVE')->first();
        if (! $task || ! $task->initiative || $task->initiative->status !== 'Active') {
            throw ValidationException::withMessages(['code' => __('govtracking::general.invalid_code')]);
        }
        $check = $this->scopeValidator->validateExecutionScope($task, $locationId);
        if (! $check['is_valid']) {
            throw ValidationException::withMessages(['code' => $check['message']]);
        }
        return $task;
    }
}
