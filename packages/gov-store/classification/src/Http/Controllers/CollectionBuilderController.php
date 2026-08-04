<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Classification\Models\CatalogCollection;
use GovStore\Classification\Models\CatalogCollectionNode;
use GovStore\Classification\Models\CatalogNode;

class CollectionBuilderController extends Controller
{
    public function index()
    {
        $collections = CatalogCollection::withCount('nodes')->orderBy('name')->paginate(20);
        return view('gov-classification::admin.collections.index', compact('collections'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
        ]);

        CatalogCollection::create([
            'name' => $request->name,
            'description' => $request->description,
            'icon' => $request->icon ?? 'fas fa-box',
            'created_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Collection created successfully.');
    }

    public function edit($id)
    {
        $collection = CatalogCollection::with(['nodes.catalogNode'])->findOrFail($id);
        return view('gov-classification::admin.collections.builder', compact('collection'));
    }

    public function attachNode(Request $request, $id)
    {
        $request->validate(['code' => 'required|string|exists:gov_catalog_nodes,code']);
        
        CatalogCollectionNode::firstOrCreate([
            'collection_id' => $id,
            'code' => $request->code
        ]);

        return response()->json(['success' => true]);
    }

    public function detachNode(Request $request, $id)
    {
        $request->validate(['code' => 'required|string']);
        
        CatalogCollectionNode::where('collection_id', $id)->where('code', $request->code)->delete();
        
        return redirect()->back()->with('success', 'Node removed from collection.');
    }
}