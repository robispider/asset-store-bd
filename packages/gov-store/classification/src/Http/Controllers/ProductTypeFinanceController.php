<?php

namespace GovStore\Classification\Http\Controllers;

use GovStore\Classification\Services\ProductTypeFinanceDefault;
use GovStore\Metadata\Services\MetadataValueStore;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** S4 · the optional Finance Code under a product type's single UNSPSC code. */
class ProductTypeFinanceController extends Controller
{
    public function update(Request $request, int $id, ProductTypeFinanceDefault $defaults, MetadataValueStore $store)
    {
        $data = $request->validate(['gov_code' => 'nullable|string|size:6', 'revision' => 'nullable|integer|min:1']);
        // The owner edits: the national publisher for national types, the owning company's admin otherwise.
        abort_unless($defaults->canEdit($request->user(), $id), 403);
        $store->put('category', $id, ProductTypeFinanceDefault::NAMESPACE, ['gov_code' => $data['gov_code'] ?? null],
            isset($data['revision']) ? (int) $data['revision'] : null, (int) auth()->id());

        return back()->with('success', __('classification::finance.default_saved'));
    }
}
