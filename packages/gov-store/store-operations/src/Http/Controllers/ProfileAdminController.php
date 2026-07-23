<?php

namespace GovStore\StoreOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GovStore\StoreOperations\Models\Profile;
use GovStore\StoreOperations\Models\ProfileAssignment;
use App\Models\Category;

class ProfileAdminController extends Controller
{
    /**
     * Renders the primary Split-Pane Studio Dashboard.
     */
    public function index()
    {
        // Group Snipe-IT Categories by native type to build the Left Pane Tree
        $categoryTree = [
            'Assets'      => Category::where('category_type', 'asset')->orderBy('name')->get(),
            'Consumables' => Category::where('category_type', 'consumable')->orderBy('name')->get(),
            'Accessories' => Category::where('category_type', 'accessory')->orderBy('name')->get(),
            'Components'  => Category::where('category_type', 'component')->orderBy('name')->get(),
            'Licenses'    => Category::where('category_type', 'license')->orderBy('name')->get(),
        ];

        $publishedProfiles = Profile::where('status', 'PUBLISHED')->orderBy('name')->get();
        
        // Fetch the plain-English dictionary from the Registry
        $dictionary = \GovStore\StoreOperations\Services\CapabilityRegistry::getDictionary();

        return view('storeops::admin.rules.index', compact('categoryTree', 'publishedProfiles', 'dictionary'));
    }

    /**
     * AJAX Endpoint: Loads the read-only summary or editor for the Right Pane.
     */
    public function inspector(Request $request)
    {
        $categoryId = $request->input('category_id');
        $category = Category::findOrFail($categoryId);

        // Find currently active assignment
        $now = now();
        $assignment = ProfileAssignment::with('profile.capabilities')
            ->where('target_type', Category::class)
            ->where('target_id', $category->id)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->first();

        // Pass available capabilities (Mocked Dictionary representation)
        $systemCapabilities = [
            'require_quantity' => ['label' => 'Quantity Required', 'group' => 'Receiving'],
            'require_serial'   => ['label' => 'Require Serial Numbers', 'group' => 'Identification'],
            'require_warranty' => ['label' => 'Capture Warranty Period', 'group' => 'Information'],
            'post_inventory'   => ['label' => 'Post to Kardex Ledger', 'group' => 'Inventory'],
            'create_assets'    => ['label' => 'Create Physical Assets', 'group' => 'Automation'],
        ];

        return view('storeops::admin.rules.partials.inspector', compact('category', 'assignment', 'systemCapabilities'));
    }

    /**
     * Creates a new Assignment (Adoption) mapping a Category to a Policy.
     */
    public function assignPolicy(Request $request)
    {
        $request->validate([
            'category_id' => 'required|integer',
            'profile_id'  => 'required|integer'
        ]);

        $now = now();

        // 1. Expire (soft delete equivalent) any existing active assignment
        ProfileAssignment::where('target_type', Category::class)
            ->where('target_id', $request->category_id)
            ->whereNull('effective_to')
            ->update(['effective_to' => $now]);

        // 2. Create the new Adoption Assignment
        ProfileAssignment::create([
            'profile_id'     => $request->profile_id,
            'target_type'    => Category::class,
            'target_id'      => $request->category_id,
            'assigned_by'    => auth()->id() ?? 1,
            'effective_from' => $now,
        ]);

        return redirect()->back()->with('success', 'Policy successfully assigned to category.');
    }
}