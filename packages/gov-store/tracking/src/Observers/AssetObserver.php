<?php

namespace GovStore\Tracking\Observers;

use App\Models\Asset;
use GovStore\Tracking\Models\TrackingReference;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Services\ValidationPolicyEngine;
use GovStore\Tracking\Services\ScopeValidatorService;
use Illuminate\Validation\ValidationException;

class AssetObserver
{
    protected ValidationPolicyEngine $policyEngine;
    protected ScopeValidatorService $scopeValidator;

    public function __construct(ValidationPolicyEngine $policyEngine, ScopeValidatorService $scopeValidator)
    {
        $this->policyEngine = $policyEngine;
        $this->scopeValidator = $scopeValidator;
    }

    public function saving(Asset $asset): void
    {
        if (request()->filled('tracking_reference_id')) {
            $reference = TrackingReference::findOrFail(request()->input('tracking_reference_id'));

            // 1. Enforce Multi-Dimensional Geographical and Organizational Scope Constraints (from Phase 2)
            $user = request()->user();
            if ($user) {
                if (!$this->scopeValidator->validateOwnership($reference, $user->company_id)) {
                    throw ValidationException::withMessages([
                        'tracking_reference_id' => ['Scope Restriction: Your Ministry is not authorized to allocate items under this reference.']
                    ]);
                }

                if (!$this->scopeValidator->validateVisibility($reference, $user->location_id, $user->company_id)) {
                    throw ValidationException::withMessages([
                        'tracking_reference_id' => ['Scope Restriction: This tracking reference is not visible within your active working context.']
                    ]);
                }
            }

            if ($asset->location_id && !$this->scopeValidator->validateApplicability($reference, $asset->location_id)) {
                throw ValidationException::withMessages([
                    'tracking_reference_id' => ['Scope Restriction: The physical destination location falls outside the applicability boundary configured for this reference.']
                ]);
            }

            // 2. Enforce Planned Targets Validation Policies
            if ($asset->model && $asset->model->category_id) {
                $this->policyEngine->evaluate($reference, $asset->model->category_id, 1);
            }
        }
    }

    public function saved(Asset $asset): void
    {
        if (request()->filled('tracking_reference_id')) {
            // Write relationship maps cleanly to association tables
            TrackingAssociation::updateOrCreate([
                'associatable_type' => Asset::class,
                'associatable_id' => $asset->id,
            ], [
                'tracking_reference_id' => request()->input('tracking_reference_id'),
                'status' => 'ACTIVE'
            ]);
        }
    }
}