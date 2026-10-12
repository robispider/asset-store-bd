<?php

namespace GovStore\UserOnboarding\Http\Controllers;

use GovStore\UserOnboarding\Models\UserOnboarding;
use GovStore\UserOnboarding\Services\OnboardingAccess;
use GovStore\UserOnboarding\Services\UserOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class UserOnboardingController extends Controller
{
    public function index(Request $request, OnboardingAccess $access)
    {
        $actor = $access->actor();
        $status = $request->validate(['status' => 'nullable|in:WAITING,COMPLETED,CANCELLED'])['status'] ?? 'WAITING';
        $queue = $access->queue($actor)->where('status', $status)->with(['user', 'creator', 'geoArea', 'events'])->orderBy('id')->paginate(25)->withQueryString();
        $managers = $queue->getCollection()->mapWithKeys(fn ($item) => [$item->id => $status === 'WAITING' ? $access->managerChoices($item) : []]);
        $locations = $access->offices($actor);
        $notices = $this->notices($actor->id);

        return view('govonboard::queue.index', compact('queue', 'locations', 'status', 'notices', 'managers'));
    }

    public function mine()
    {
        $item = UserOnboarding::where('user_id', auth()->id())->with('membership.location')->first();
        $notices = $this->notices(auth()->id());

        // Reasons and other recipients are private administrative history.
        return view('govonboard::queue.mine', compact('item', 'notices'));
    }

    private function notices(int $id)
    {
        return DB::table('gov_onboarding_notices as n')->leftJoin('locations as l', 'l.id', '=', 'n.location_id')
            ->where('n.user_id', $id)->orderByDesc('n.id')->limit(20)->get(['n.event_key', 'n.created_at', 'l.name as office']);
    }

    public function assign(Request $request, UserOnboardingService $service)
    {
        $data = $request->validate(['onboarding_id' => 'required|integer|min:1', 'location_id' => 'required|integer|min:1']);
        $service->assignToOffice($data['onboarding_id'], $data['location_id']);

        return redirect()->route('gov.onboard.index')->with('success', __('govonboard::onboard.assigned'));
    }

    public function decide(Request $request, UserOnboardingService $service)
    {
        $data = $request->validate(['onboarding_id' => 'required|integer|min:1', 'action' => 'required|in:cancel,reject,reassign,reopen',
            'reason' => 'required|string|min:5|max:1000', 'owner_id' => 'nullable|integer|min:1', 'owner_type' => 'nullable|in:OFFICE_ADMIN,COMPANY_ADMIN,ICT_OFFICER',
            'owner_choice' => ['nullable', 'regex:/^(OFFICE_ADMIN|COMPANY_ADMIN|ICT_OFFICER):[1-9][0-9]*$/']]);
        if (! empty($data['owner_choice'])) {
            [$data['owner_type'], $data['owner_id']] = explode(':', $data['owner_choice']);
        }
        $service->decide($data['onboarding_id'], $data['action'], $data['reason'], $data['owner_id'] ?? null, $data['owner_type'] ?? null);

        return redirect()->route('gov.onboard.index')->with('success', __('govonboard::onboard.updated'));
    }
}
