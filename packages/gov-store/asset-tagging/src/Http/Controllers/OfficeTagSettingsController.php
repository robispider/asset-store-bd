<?php

namespace GovStore\AssetTagging\Http\Controllers;

use GovStore\AssetTagging\Services\EnablementGate;
use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\Metadata\Services\MetadataValueStore;
use GovStore\Metadata\Support\StaleMetadataRevision;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** S5 · office tab: the working office picks its registration location and divisions from the company lists. */
class OfficeTagSettingsController extends Controller
{
    public function __construct(private OfficeTagFacts $facts, private MetadataValueStore $store) {}

    public function show(Request $request, EnablementGate $gate)
    {
        $officeId = (int) app(TenantContext::class)->locationId;
        abort_unless($officeId, 404);
        $companyId = $this->facts->companyOf($officeId);
        $row = $this->store->find('office', $officeId, OfficeTagFacts::OFFICE);

        return view('asset-tagging::office.settings', [
            'tab' => 'office',
            'office' => \Illuminate\Support\Facades\DB::table('locations')->where('id', $officeId)->first(['id', 'name']),
            'lists' => $this->facts->codeLists($companyId),
            'taken' => $companyId ? $this->facts->takenLocations($companyId, $officeId) : [],
            'current' => $row?->payload,
            'row' => $row,
            'gates' => $gate->all($officeId),
            'enabled' => $this->facts->enabled($officeId),
            'conflict' => session('asset_tags_conflict'),
            'canCodes' => app(GovAccess::class)->decide($request->user(), 'asset-tags.company.codelists')->allowed,
        ]);
    }

    public function update(Request $request)
    {
        $officeId = (int) app(TenantContext::class)->locationId;
        abort_unless($officeId, 404);
        $data = $request->validate(['location_code' => 'required|digits:2', 'division_codes' => 'required|array|min:1|max:99',
            'division_codes.*' => 'digits:2', 'default_division_code' => 'required|digits:2', 'revision' => 'nullable|integer|min:1']);
        try {
            $this->store->put('office', $officeId, OfficeTagFacts::OFFICE, $data,
                isset($data['revision']) ? (int) $data['revision'] : null, (int) auth()->id());
        } catch (StaleMetadataRevision $conflict) {
            // Compare and choose: show the saved version next to what this user submitted.
            return back()->withInput()->with('asset_tags_conflict', ['theirs' => $conflict->current?->payload, 'revision' => $conflict->current?->revision]);
        }

        return redirect()->route('asset-tags.office.show')->with('success', __('asset-tagging::tagging.office.saved'));
    }
}
