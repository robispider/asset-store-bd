<?php

namespace GovStore\UserOnboarding\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\UserOnboarding\Models\UserOnboarding;
use GovStore\UserOnboarding\Services\UserOnboardingService;
use App\Models\User;
use App\Models\Location;
use Exception;

class UserOnboardingController extends Controller
{
    private function checkAccess()
    {
        $user = auth()->user();
        if ($user->isSuperUser() || $user->hasAccess('admin')) return;

        // ICT Officers or Company Admins have access to the onboarding queue
        $isIctOfficer = \GovStore\Organization\Models\IctJurisdiction::where('user_id', $user->id)->exists();
        $isCompanyAdmin = \GovStore\Organization\Models\CompanyAdmin::where('user_id', $user->id)->exists();

        if (!$isIctOfficer && !$isCompanyAdmin) {
            abort(403, 'Unauthorized access to the Onboarding Queue.');
        }
    }

    public function index()
    {
        $this->checkAccess();

        // Query waiting orphans (Bypassing UserScope to let admins see unassigned users in their bounds)
        $queue = UserOnboarding::where('status', 'WAITING')
            ->whereIn('user_id', function ($query) {
                // Let UserScope handle the geographic filter calculation natively
                $query->select('id')->from('users')->whereNull('location_id');
            })
            ->with(['user', 'creator', 'geoArea'])
            ->paginate(25);

        // Fetch visible operational locations for assignment
        $locations = Location::orderBy('name')->get();

        return view('govonboard::queue.index', compact('queue', 'locations'));
    }

    public function assign(Request $request, UserOnboardingService $service)
    {
        $this->checkAccess();

        $request->validate([
            'onboarding_id' => 'required|integer',
            'location_id'   => 'required|integer|exists:locations,id',
        ]);

        try {
            $service->assignToOffice($request->onboarding_id, $request->location_id);
            return redirect()->back()->with('success', 'User successfully assigned to office and activated.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
