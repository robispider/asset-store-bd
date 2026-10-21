<?php

namespace GovStore\AssetTagging\Http\Controllers;

use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\Metadata\Services\MetadataValueStore;
use GovStore\Metadata\Support\StaleMetadataRevision;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** S5 · company tab: the company administrator keeps the location and division code lists. */
class CompanyCodeListController extends Controller
{
    public function __construct(private OfficeTagFacts $facts, private MetadataValueStore $store) {}

    public function show(Request $request)
    {
        $companyId = (int) app(TenantContext::class)->companyId;
        abort_unless($companyId, 404);
        $row = $this->store->find('company', $companyId, OfficeTagFacts::CODELISTS);

        return view('asset-tagging::office.codelists', [
            'tab' => 'codes',
            'company' => \Illuminate\Support\Facades\DB::table('companies')->where('id', $companyId)->first(['id', 'name']),
            'lists' => $this->facts->codeLists($companyId),
            'usage' => $this->facts->usage($companyId),
            'row' => $row,
            'conflict' => session('asset_tags_conflict'),
            'canOffice' => app(GovAccess::class)->decide($request->user(), 'asset-tags.office.configure')->allowed,
        ]);
    }

    public function update(Request $request)
    {
        $companyId = (int) app(TenantContext::class)->companyId;
        abort_unless($companyId, 404);
        $data = $request->validate([
            'locations' => 'nullable|array|max:120', 'locations.*.code' => 'nullable|string|max:2', 'locations.*.name' => 'nullable|string|max:120',
            'divisions' => 'nullable|array|max:120', 'divisions.*.code' => 'nullable|string|max:2', 'divisions.*.name' => 'nullable|string|max:120',
            'revision' => 'nullable|integer|min:1',
        ]);
        try {
            $this->store->put('company', $companyId, OfficeTagFacts::CODELISTS,
                ['locations' => $data['locations'] ?? [], 'divisions' => $data['divisions'] ?? []],
                isset($data['revision']) ? (int) $data['revision'] : null, (int) auth()->id());
        } catch (StaleMetadataRevision $conflict) {
            return back()->withInput()->with('asset_tags_conflict', ['theirs' => $conflict->current?->payload, 'revision' => $conflict->current?->revision]);
        }

        return redirect()->route('asset-tags.codes.show')->with('success', __('asset-tagging::tagging.codes.saved'));
    }
}
