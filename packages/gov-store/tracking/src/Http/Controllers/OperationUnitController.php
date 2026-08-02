<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\OperationUnit;
use GovStore\Tracking\Services\TrackingAuthorizationService; // Added
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class OperationUnitController extends Controller
{
    protected TrackingAuthorizationService $authService;

    public function __construct(TrackingAuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    public function index(Initiative $initiative)
    {
        $initiative->load(['operationUnits.user']);

        $head = $initiative->operationUnits->where('designation', 'HEAD')->first();
        $officers = $initiative->operationUnits->where('designation', 'OFFICER');
        $support = $initiative->operationUnits->where('designation', 'SUPPORT');

        return view('govtracking::operation_unit.index', compact('initiative', 'head', 'officers', 'support'));
    }

    public function searchUsers(Request $request)
    {
        $request->validate([
            'q'             => 'nullable|string',
            'initiative_id' => 'required|exists:gov_initiatives,id',
        ]);

        $term = $request->input('q');
        $initiative = Initiative::findOrFail($request->input('initiative_id'));

        $users = DB::table('users')
            ->join('company_user', 'users.id', '=', 'company_user.user_id')
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.employee_num')
            ->where('company_user.company_id', $initiative->owner_company_id)
            ->whereNull('users.deleted_at')
            ->where(function($query) use ($term) {
                $query->where('users.first_name', 'LIKE', "%{$term}%")
                      ->orWhere('users.last_name', 'LIKE', "%{$term}%")
                      ->orWhere('users.username', 'LIKE', "%{$term}%")
                      ->orWhere('users.employee_num', 'LIKE', "%{$term}%");
            })
            ->limit(20)
            ->get();

        $results = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'text' => "{$user->first_name} {$user->last_name} ({$user->username})" . ($user->employee_num ? " - {$user->employee_num}" : '')
            ];
        });

        return response()->json(['results' => $results]);
    }

    public function store(Request $request, Initiative $initiative)
    {
        // GATED (Centralized): Only Project Director (HEAD) or Ministry Admin can manage team roster
        $this->authService->authorize($initiative, ['HEAD']);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'designation' => 'required|in:HEAD,OFFICER,SUPPORT,MONITOR',
        ]);

        $designation = $request->input('designation');
        $userId = $request->input('user_id');

        if ($designation === 'HEAD') {
            $existingHead = OperationUnit::where('initiative_id', $initiative->id)
                ->where('designation', 'HEAD')
                ->first();

            if ($existingHead) {
                throw ValidationException::withMessages([
                    'user_id' => 'An Operation Head is already designated for this Initiative. You must remove the current Head before assigning a replacement.'
                ]);
            }
        }

        $duplicate = OperationUnit::where('initiative_id', $initiative->id)
            ->where('user_id', $userId)
            ->where('designation', $designation)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'user_id' => 'This user is already assigned to this specific role.'
            ]);
        }

        OperationUnit::create([
            'initiative_id' => $initiative->id,
            'user_id' => $userId,
            'designation' => $designation,
        ]);

        return redirect()->back()->with('success', 'Staff member successfully assigned to the Operation Unit.');
    }

    public function destroy(Initiative $initiative, OperationUnit $unit)
    {
        // GATED (Centralized): Only Project Director (HEAD) or Ministry Admin can manage team roster
        $this->authService->authorize($initiative, ['HEAD']);

        if ($unit->initiative_id !== $initiative->id) {
            abort(403, 'Invalid Operation Unit association.');
        }

        $unit->delete();

        return redirect()->back()->with('success', 'Staff member designation removed.');
    }
}