<?php

namespace GovStore\Organization\Http\Controllers;

use GovStore\Organization\Services\OfficeLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OfficeLifecycleController extends Controller
{
    public function update(Request $request, int $id, OfficeLifecycleService $service)
    {
        $service->transition($id, (int) $request->user()->id, $request->only([
            'action', 'expected_status', 'expected_geo_area_id', 'reason', 'confirmation', 'geo_area_id',
        ]));

        return redirect()->route('gov.org.hub.show', $id)
            ->with('success', __('organization_labels::orglabel.lifecycle_saved'));
    }
}
