<?php

namespace GovStore\CustomRequests\Http\Controllers;

use App\Models\Accessory;
use App\Models\AssetModel;
use App\Models\Consumable;
use GovStore\CustomRequests\Services\CatalogService;
use GovStore\CustomRequests\Services\RequesterService;
use GovStore\CustomRequests\Services\RequestInventory;
use GovStore\CustomRequests\Services\RequestReturnService;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GovRequestController extends Controller
{
    public function index()
    {
        // Fetch only submitted requests made by the currently logged-in user (excluding active drafts)
        $requests = \GovStore\CustomRequests\Models\Request::with(['items.requested'])
            ->where('requested_by', auth()->id())
            ->where('approval_status', '!=', 'draft') // Hide drafts from their history list
            ->orderBy('created_at', 'desc')
            ->get();

        return view('govstore::user.index', compact('requests'));
    }

    public function catalog(CatalogService $catalogService)
    {
        $userId = auth()->id();

        $catalogItems = $catalogService->paginate(request()->validate(['q' => 'nullable|string|max:200', 'type' => 'nullable|in:asset_model,accessory,consumable', 'category_id' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1']));

        // 2. Fetch the user's request counts using the correct 'approval_status' column
        // (Drafts are excluded; we count submitted, approved/in-progress, and rejected)
        $pendingCount = \GovStore\CustomRequests\Models\Request::where('requested_by', $userId)
            ->whereIn('approval_status', ['pending_primary', 'pending_final'])
            ->count();

        $approvedCount = \GovStore\CustomRequests\Models\Request::where('requested_by', $userId)
            ->whereIn('approval_status', ['approved', 'partially_approved'])
            ->count();

        $rejectedCount = \GovStore\CustomRequests\Models\Request::where('requested_by', $userId)
            ->where('approval_status', 'rejected')
            ->count();

        return view('govstore::catalog.index', compact('catalogItems', 'pendingCount', 'approvedCount', 'rejectedCount'));
    }

    public function withdraw($id, RequesterService $service)
    {
        $service->transition((int) $id, auth()->user(), 'withdraw');

        return redirect()->back()->with('success', __('requestlabels::requests.request_withdrawn'));
    }

    public function receive($id, RequesterService $service)
    {
        $service->transition((int) $id, auth()->user(), 'receive');

        return redirect()->back()->with('success', __('requestlabels::requests.receipt_recorded'));
    }

    public function search(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:200', 'type' => 'required|in:asset_model,consumable,accessory']);
        $term = $request->input('q', '');
        $type = strtolower($request->input('type', ''));

        if (empty($term) || empty($type)) {
            return response()->json([]);
        }

        $results = [];
        $office = app(TenantContext::class)->locationId;
        abort_unless($office, 422);
        $inventory = app(RequestInventory::class);

        // Query Snipe-IT's core tables directly with optimized limits
        if ($type === 'consumable') {
            $items = $inventory->scopeCompany(Consumable::where('location_id', $office))->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => $item->name.' ('.__('requestlabels::requests.stock_available').': '.app(RequestInventory::class)->available('consumable', $item, $office).')'];
            }
        } elseif ($type === 'accessory') {
            $items = $inventory->scopeCompany(Accessory::where('location_id', $office))->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => $item->name.' ('.__('requestlabels::requests.stock_available').': '.app(RequestInventory::class)->available('accessory', $item, $office).')'];
            }
        } elseif ($type === 'asset_model') {
            $items = AssetModel::where('name', 'like', "%{$term}%")
                ->whereHas('assets', fn ($q) => $inventory->scopeCompany($q, 'assets.company_id')->where('location_id', $office)->whereNull('assigned_to')->where('requestable', 1)
                    ->whereHas('status', fn ($q) => $q->where('deployable', 1)->where('archived', 0)))->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => $item->name];
            }
        }

        return response()->json($results);
    }

    public function requestReturn(Request $request, $id, RequestReturnService $service)
    {
        $request->validate(['reason' => 'required|string|min:5|max:2000']);
        $service->requestReturn((int) $id, auth()->user(), $request->input('reason'));

        return redirect()->back()->with('success', __('requestlabels::requests.event_return_requested'));
    }
}
