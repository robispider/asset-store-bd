<?php

namespace GovStore\Classification\Http\Controllers\Api;

use GovStore\Classification\Http\Transformers\FinanceItemsTransformer;
use GovStore\Classification\Models\FinanceItem;
use GovStore\Classification\Services\ProductTypeCommodity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FinanceItemsController extends Controller
{
    /**
     * Active Finance items for a select2 picker. With category_id, items linked to a different UNSPSC
     * are excluded on the server and items matching the product type's code are listed first.
     */
    public function selectlist(Request $request, ProductTypeCommodity $commodity)
    {
        $request->validate(['search' => 'nullable|string|max:100', 'category_id' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1']);
        $unspsc = $request->filled('category_id') ? $commodity->codeFor((int) $request->input('category_id')) : null;

        $query = FinanceItem::query()->where('status', 'active');
        if ($request->filled('category_id')) {
            $query->where(fn ($q) => $q->whereNull('unspsc_code')->when($unspsc, fn ($q) => $q->orWhere('unspsc_code', $unspsc)));
            if ($unspsc) {
                $query->orderByRaw('CASE WHEN unspsc_code = ? THEN 0 ELSE 1 END', [$unspsc]);
            }
        }
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(fn ($q) => $q->where('gov_code', 'like', $term.'%')->orWhere('item_name', 'like', '%'.$term.'%')
                ->orWhere('subgroup_name', 'like', '%'.$term.'%')->orWhere('group_name', 'like', '%'.$term.'%'));
        }
        $items = $query->orderBy('gov_code')->paginate(25);

        return response()->json((new FinanceItemsTransformer)->transformSelectList($items, $unspsc));
    }
}
