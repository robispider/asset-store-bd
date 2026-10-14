<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Organization\Models\CompanyAdmin;
use App\Models\User;
use App\Models\Company;

class CompanyAdminController extends Controller
{
    private function checkSuperadminAccess()
    {
        $user = auth()->user();
        if (!$user->isSuperUser()) {
            abort(403, __('organization_labels::orglabel.company_admin_unauthorized'));
        }
    }

public function index()
    {
        $this->checkSuperadminAccess();

        // Load active assignments
        $admins = CompanyAdmin::with(['user.location', 'company'])->get();
        
        // Fixed: Use withoutGlobalScopes() to ensure unassigned/new users are visible in the dropdown
        $users = User::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('first_name')->get();
        $companies = Company::orderBy('name')->get();
        $ictCount = \GovStore\Organization\Models\IctJurisdiction::count();

        return view('govorg::provisioning.company-admins', compact('admins', 'users', 'companies', 'ictCount'));
    }
    public function store(Request $request)
    {
        $this->checkSuperadminAccess();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id,deleted_at,NULL',
            'company_id' => 'required|integer|exists:companies,id,deleted_at,NULL',
        ], [], ProvisioningController::attributes() + ['company_id' => __('organization_labels::office.ministry_pick')]);

        try {
            app(\GovStore\Organization\Services\OfficeAdministration::class)->actor((int) $request->user_id);
            // A user can only manage one company. updateOrCreate handles overwriting gracefully.
            $admin = CompanyAdmin::updateOrCreate(
                ['user_id' => $request->user_id],
                ['company_id' => $request->company_id]
            );
            $admin->load(['user', 'company']);

            return redirect()->route('gov.org.company_admins.index')->with('success', __('organization_labels::office.granted_ministry', [
                'name' => $admin->user?->present()->fullName ?? '', 'ministry' => $admin->company?->name ?? '',
            ]));
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function destroy($id)
    {
        $this->checkSuperadminAccess();

        try {
            $admin = CompanyAdmin::with('user')->findOrFail($id);
            $restore = ['route' => 'gov.org.company_admins.store', 'fields' => ['user_id' => $admin->user_id, 'company_id' => $admin->company_id]];
            $name = $admin->user?->present()->fullName ?? '';
            $admin->delete();

            return redirect()->route('gov.org.company_admins.index')
                ->with('success', __('organization_labels::office.removed_ministry', ['name' => $name]))
                ->with('gov_org_restore', $restore);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
