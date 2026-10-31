<?php

namespace GovStore\TenantScope\Http\Controllers;

use App\Models\Company;
use App\Models\Location;
use GovStore\TenantScope\Services\ReferenceOwnershipService;
use GovStore\TenantScope\Services\ReferenceTypes;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ReferenceOwnershipController extends Controller
{
    public function index()
    {
        $owners = DB::table('gov_reference_owners')->orderByRaw("CASE WHEN owner_type = 'unresolved' THEN 0 ELSE 1 END")->orderBy('id')->paginate(50);
        foreach ($owners as $owner) {
            $owner->reference_name = ReferenceTypes::MODELS[$owner->reference_type]::withoutGlobalScopes()->whereKey($owner->reference_id)->value('name');
        }

        return view('govscope::admin.ownership', ['owners' => $owners,
            'ownershipCounts' => DB::table('gov_reference_owners')->select('owner_type')->selectRaw('COUNT(*) AS total')->groupBy('owner_type')->pluck('total', 'owner_type'),
            'companies' => Company::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            'offices' => Location::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isSuperUser() && $request->attributes->get('gov_national_reviewed') === 'tenant.scope.manage', 403);
        if ($request->filled('owner_choice')) {
            $request->validate(['owner_choice' => ['required', 'regex:/^(national|(company|office):[1-9][0-9]*)$/']]);
            $parts = explode(':', $request->input('owner_choice'));
            $request->merge(['owner_type' => $parts[0], 'owner_id' => $parts[1] ?? null]);
        }
        $data = $request->validate(['reference_type' => 'required|in:'.implode(',', array_keys(ReferenceTypes::MODELS)),
            'reference_id' => 'required|integer|min:1', 'owner_type' => 'required|in:national,company,office',
            'owner_id' => 'nullable|required_unless:owner_type,national|integer|min:1']);
        DB::transaction(function () use ($data, $request) {
            $model = ReferenceTypes::MODELS[$data['reference_type']]::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($data['reference_id']);
            app(ReferenceOwnershipService::class)->assign($model, $data['owner_type'], isset($data['owner_id']) ? (int) $data['owner_id'] : null,
                (int) $request->user()->id, (string) $request->input('change_reason'));
        });

        return back()->with('success', __('tenantops::access.reviewed'));
    }
}
