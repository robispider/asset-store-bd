<?php

namespace GovStore\Classification\Http\Controllers;

use GovStore\Classification\Models\FinanceItem;
use GovStore\Classification\Services\FinanceCatalog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * S6 · the national Finance item list. Every POST runs under catalog.master.manage, so tenant-scope's
 * national change review freezes the exact payload, asks for a typed confirmation and is single-use.
 */
class FinanceCatalogController extends Controller
{
    public function __construct(private FinanceCatalog $catalog) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['status' => 'nullable|in:held,active,retired,all', 'q' => 'nullable|string|max:100',
            'group' => 'nullable|digits:2', 'new_group' => 'nullable|digits:2']);
        $status = $filters['status'] ?? 'held';
        $items = FinanceItem::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['group'] ?? null, fn ($q, $group) => $q->where('gov_code', 'like', $group.'%'))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('gov_code', 'like', $term.'%')
                ->orWhere('item_name', 'like', '%'.$term.'%')->orWhere('subgroup_name', 'like', '%'.$term.'%')))
            ->orderBy('gov_code')->paginate(50)->withQueryString();
        $capacity = $this->catalog->capacity();
        $newGroup = $filters['new_group'] ?? null;
        $preview = $newGroup ? ['group' => $newGroup, 'next' => $this->catalog->nextItemNumber($newGroup),
            'subgroups' => FinanceItem::where('gov_code', 'like', $newGroup.'%')->get(['gov_code', 'subgroup_name'])
                ->unique(fn ($item) => substr($item->gov_code, 2, 2))->mapWithKeys(fn ($item) => [substr($item->gov_code, 2, 2) => $item->subgroup_name])->all()] : null;

        return view('gov-classification::finance.index', ['items' => $items, 'counts' => $this->catalog->counts(), 'capacity' => $capacity,
            'status' => $status, 'filters' => $filters, 'preview' => $preview, 'catalog' => $this->catalog]);
    }

    public function activate(Request $request)
    {
        $data = $request->validate(['codes' => 'required|array|min:1|max:500', 'codes.*' => 'string|size:6',
            'authority_reference' => 'required|string|min:3|max:500']);
        $count = $this->catalog->activate($data['codes'], $data['authority_reference'], (int) auth()->id());

        return back()->with('success', __('classification::finance.activated', ['count' => $count]));
    }

    public function retire(Request $request)
    {
        $data = $request->validate(['codes' => 'required|array|min:1|max:500', 'codes.*' => 'string|size:6']);

        return back()->with('success', __('classification::finance.retired', ['count' => $this->catalog->retire($data['codes'], (int) auth()->id())]));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['group_code' => 'required|digits:2|not_in:00', 'subgroup_code' => 'required|digits:2|not_in:00',
            'group_name' => 'nullable|string|max:191', 'subgroup_name' => 'nullable|string|max:191',
            'item_name' => 'required|string|min:2|max:255', 'unspsc_code' => 'nullable|string|max:50',
            'authority_reference' => 'nullable|string|max:500']);
        $item = $this->catalog->create($data, (int) auth()->id());

        return redirect()->route('gov.catalog.finance.index', ['status' => 'held', 'q' => $item->gov_code])
            ->with('success', __('classification::finance.created', ['code' => $item->gov_code]));
    }

    public function link(Request $request, string $code)
    {
        $data = $request->validate(['unspsc_code' => 'nullable|string|max:50']);
        $this->catalog->linkUnspsc($code, $data['unspsc_code'] ?? null, (int) auth()->id());

        return back()->with('success', __('classification::finance.linked', ['code' => $code]));
    }
}
