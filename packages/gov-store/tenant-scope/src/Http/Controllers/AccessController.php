<?php

namespace GovStore\TenantScope\Http\Controllers;

use GovStore\OfficeMembership\Services\RoleAssignmentService;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AccessController extends Controller
{
    public const REQUESTABLE = [
        'committee.manage' => 'committee_registrar',
        'committee.activate' => 'committee_registrar',
        'storeops.documents.view' => 'primary_approver',
        'storeops.documents.draft' => 'storekeeper',
        'storeops.documents.post' => 'storekeeper',
        'catalog.office.adopt' => 'storekeeper',
        'requests.approve' => 'primary_approver',
        'requests.fulfill' => 'storekeeper',
    ];

    public function index(Request $request, GovAccess $access, TenantContext $context)
    {
        $roles = $access->roles($request->user());
        $abilities = array_filter(config('govstore-abilities'), fn ($ability) => $access->decide($request->user(), $ability)->allowed, ARRAY_FILTER_USE_KEY);
        $requests = DB::table('gov_access_requests')->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(20);
        $selected = isset(self::REQUESTABLE[$request->query('ability', '')]) ? $request->query('ability') : '';
        $requestable = ! $request->filled('ability') || isset(self::REQUESTABLE[$request->query('ability')]);

        return view('govscope::access.index', compact('roles', 'abilities', 'requests', 'selected', 'context', 'requestable'));
    }

    public function store(Request $request, TenantContext $context)
    {
        $data = $request->validate(['ability' => 'required|in:'.implode(',', array_keys(self::REQUESTABLE)),
            'reason' => 'required|string|min:5|max:1000', 'expires_at' => 'nullable|date|after:today']);
        abort_unless($context->locationId, 422);
        abort_unless($this->belongsToOffice($request->user()->id, $context->locationId), 403);
        DB::transaction(function () use ($data, $request, $context) {
            // Serialize submissions by locking the requester, then check for a pending duplicate.
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            if (DB::table('gov_access_requests')->where('user_id', $request->user()->id)->where('location_id', $context->locationId)
                ->where('ability', $data['ability'])->where('status', 'pending')->exists()) {
                return;
            }
            $id = DB::table('gov_access_requests')->insertGetId($data + ['user_id' => $request->user()->id,
                'location_id' => $context->locationId, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $adminId = LocationProfile::where('location_id', $context->locationId)->value('office_admin_id');
            if ($adminId) {
                $this->notify($adminId, 'notification_request', $id);
            }
        });

        return redirect()->route('gov.access.index')->with('success', __('tenantops::access.saved'));
    }

    public function inbox(TenantContext $context)
    {
        abort_unless($context->locationId, 422);
        $requests = DB::table('gov_access_requests')->where('location_id', $context->locationId)->orderByDesc('id')->paginate(30);

        return view('govscope::access.inbox', compact('requests'));
    }

    public function review(Request $request, int $id, TenantContext $context, RoleAssignmentService $service)
    {
        $data = $request->validate(['decision' => 'required|in:approved,declined', 'review_note' => 'required_if:decision,declined|nullable|string|max:1000']);
        DB::transaction(function () use ($id, $context, $data, $request, $service) {
            $entry = DB::table('gov_access_requests')->where('location_id', $context->locationId)->where('id', $id)->lockForUpdate()->first();
            abort_unless($entry, 404);
            abort_unless($entry->status === 'pending', 409);
            abort_if((int) $entry->user_id === (int) $request->user()->id, 403);
            abort_unless(isset(self::REQUESTABLE[$entry->ability]) && $this->belongsToOffice($entry->user_id, $entry->location_id), 422);
            if ($data['decision'] === 'approved') {
                abort_if($entry->expires_at && now()->gte($entry->expires_at), 422);
                $service->grantOfficeAccess($entry->location_id, self::REQUESTABLE[$entry->ability], $entry->user_id,
                    $request->user()->id, $entry->expires_at, $entry->id);
            }
            DB::table('gov_access_requests')->where('id', $id)->update(['status' => $data['decision'],
                'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
            $this->notify($entry->user_id, 'notification_review', $entry->id);
        });

        return back()->with('success', __('tenantops::access.reviewed'));
    }

    private function belongsToOffice(int $userId, int $locationId): bool
    {
        return DB::table('users')->where('id', $userId)->whereNull('deleted_at')->where(function ($q) use ($locationId, $userId) {
            $q->where('location_id', $locationId)->orWhereExists(function ($sub) use ($locationId, $userId) {
                $sub->selectRaw('1')->from('gov_office_memberships')->where('user_id', $userId)
                    ->where('location_id', $locationId)->where('status', 'active');
            });
        })->exists();
    }

    private function notify(int $userId, string $message, int $requestId): void
    {
        // Durable, in-app notices; no external email or messages are sent.
        DB::table('gov_access_notices')->insert(['user_id' => $userId, 'message_key' => $message,
            'access_request_id' => $requestId, 'created_at' => now()]);
    }

    public function matrix(Request $request)
    {
        $matrix = config('govstore-abilities');
        $routes = [];
        foreach (app('router')->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (str_starts_with($middleware, 'gov.can:')) {
                    $routes[substr($middleware, 8)][] = implode('|', $route->methods()).' '.$route->uri();
                }
            }
        }
        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($matrix, $routes) {
                $stream = fopen('php://output', 'w');
                fputcsv($stream, ['Ability', 'Roles', 'Routes'], ',', '"', '');
                foreach ($matrix as $ability => $definition) {
                    fputcsv($stream, [$ability, implode('|', $definition['roles']), implode('|', $routes[$ability] ?? [])], ',', '"', '');
                }
                fclose($stream);
            }, 'gov-store-permissions.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        $roles = array_values(array_unique(array_merge(...array_column($matrix, 'roles'))));

        return view('govscope::access.matrix', compact('matrix', 'routes', 'roles'));
    }

    public function audit(Request $request)
    {
        $query = DB::table('gov_access_events');
        foreach (['location_id', 'ability', 'outcome'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        $events = $query->orderByDesc('id')->paginate(50)->withQueryString();

        return view('govscope::access.audit', compact('events'));
    }

    public function shadow(TenantContext $context)
    {
        $query = DB::table('gov_access_events')->where('outcome', 'shadow');
        // ICT users see aggregate events only inside their visible jurisdiction.
        if (! auth()->user()->isSuperUser()) {
            $query->whereIn('location_id', $context->allowedLocationIds ?? []);
        }
        $events = $query->select('ability', 'roles', 'location_id')->selectRaw('COUNT(*) as total')
            ->groupBy('ability', 'roles', 'location_id')->orderBy('location_id')->paginate(50);

        return view('govscope::access.shadow', compact('events'));
    }
}
