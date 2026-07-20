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
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
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

        return view('govorg::provisioning.company-admins', compact('admins', 'users', 'companies'));
    }
    public function store(Request $request)
    {
        $this->checkSuperadminAccess();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        try {
            // A user can only manage one company. updateOrCreate handles overwriting gracefully.
            CompanyAdmin::updateOrCreate(
                ['user_id' => $request->user_id],
                ['company_id' => $request->company_id]
            );

            return redirect()->back()->with('success', __('organization_labels::orglabel.company_admin_assigned_success'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $this->checkSuperadminAccess();

        try {
            CompanyAdmin::findOrFail($id)->delete();
            return redirect()->back()->with('success', __('organization_labels::orglabel.company_admin_revoked_success'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}