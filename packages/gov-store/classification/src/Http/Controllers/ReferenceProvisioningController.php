<?php

namespace GovStore\Classification\Http\Controllers;

use App\Models\Category;
use App\Models\Manufacturer;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use GovStore\TenantScope\Services\ReferenceCreation;
use GovStore\TenantScope\Services\ReferenceOwnershipService;
use GovStore\TenantScope\Services\ReferenceTypes;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ReferenceProvisioningController extends Controller
{
    public function create()
    {
        return view('gov-classification::references.create', ['categories' => Category::where('category_type', 'asset')->orderBy('name')->get(['id', 'name']),
            'manufacturers' => Manufacturer::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request, TenantContext $context)
    {
        $data = $request->validate(['reference_type' => 'required|in:model,supplier,manufacturer',
            'name' => 'required|string|max:255', 'category_id' => 'nullable|required_if:reference_type,model|prohibited_unless:reference_type,model|integer',
            'manufacturer_id' => 'nullable|prohibited_unless:reference_type,model|integer',
            'model_number' => 'nullable|string|max:255']);
        abort_unless(app(GovAccess::class)->decide($request->user(),
            $context->isGlobal ? 'catalog.master.manage' : 'catalog.office.adopt')->allowed, 403);
        abort_unless($context->isGlobal || ($context->companyId && ($context->isCompanyAdmin || $context->locationId)), 422);
        abort_if($context->isGlobal && $request->attributes->get('gov_national_reviewed') !== 'catalog.master.manage', 403);

        return DB::transaction(function () use ($request, $context, $data) {
            $class = ReferenceTypes::MODELS[$data['reference_type']];
            $model = new $class;
            $model->name = $data['name'];
            if ($data['reference_type'] === 'model') {
                $category = Category::findOrFail($data['category_id'] ?? 0);
                abort_unless($category->category_type === 'asset', 422);
                $model->category_id = $category->id;
                if (! empty($data['manufacturer_id'])) {
                    $model->manufacturer_id = Manufacturer::findOrFail($data['manufacturer_id'])->id;
                }
                $model->model_number = $data['model_number'] ?? null;
            }
            abort_unless(app(ReferenceCreation::class)->run($class, fn () => $model->save()), 422);
            $scope = $context->isGlobal ? 'national' : ($context->isCompanyAdmin ? 'company' : 'office');
            $id = $scope === 'national' ? null : ($scope === 'company' ? $context->companyId : $context->locationId);
            app(ReferenceOwnershipService::class)->assign($model, $scope, $id, (int) $request->user()->id, 'Scoped reference provisioning.');
            if ($id) {
                DB::table('gov_tenant_scope_mappings')->insert(['reference_type' => $data['reference_type'], 'reference_id' => $model->id,
                    'scope_type' => $scope === 'office' ? 'location' : 'company', 'scope_id' => $id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }

            return redirect()->route('gov.catalog.references.create')->with('success', __('tenantops::access.saved'));
        });
    }
}
