<?php

namespace GovStore\Classification\Http\Livewire;

use Livewire\Component;
use GovStore\Classification\Services\CatalogSearchService;

class CatalogSearchDropdown extends Component
{
    public $query = '';
    public $suggestions = [];
    public $selectedSnipeCategoryId = null;

    /**
     * Triggered when the query input changes.
     * Fetches suggestions if query length > 2 characters.
     */
    public function updatedQuery()
    {
        if (strlen($this->query) > 2) {
            $this->suggestions = app(CatalogSearchService::class)->search($this->query, 'UNSPSC', 5);
        } else {
            $this->suggestions = [];
        }
    }

    /**
     * Handle selection of a suggestion.
     * If mapped: auto-fills the Snipe-IT category field silently.
     * If not mapped: triggers the mapping prompt wizard.
     */
    public function selectItem($code)
    {
        // Find the node from our loaded suggestions
        $item = collect($this->suggestions)->firstWhere('code', $code);

        if ($item && $item->snipeMapping) {
            // Mapped: Auto-fill the underlying Snipe-IT category form field silently
            $this->selectedSnipeCategoryId = $item->snipeMapping->category_id;
            $this->query = $item->title_en;
            $this->suggestions = [];

            // Dispatch browser event to update standard Snipe-IT select2 UI
            $this->dispatch('category-auto-selected', id: $this->selectedSnipeCategoryId);
        } else {
            // Not mapped: Trigger the mapping prompt wizard for the user
            $this->dispatch('prompt-category-mapping', code: $code, title: $item->title_en ?? '');
        }
    }

    /**
     * Render the Livewire component view.
     */
    public function render()
    {
        return view('gov-classification::livewire.catalog-search-dropdown');
    }
}
