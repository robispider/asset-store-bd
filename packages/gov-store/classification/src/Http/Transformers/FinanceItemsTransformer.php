<?php

namespace GovStore\Classification\Http\Transformers;

use GovStore\Classification\Models\FinanceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FinanceItemsTransformer
{
    /** select2 payload: one option per item, "090809 · Laptop" with the group path and the UNSPSC match flag. */
    public function transformSelectList(LengthAwarePaginator $items, ?string $productTypeUnspsc): array
    {
        return [
            'results' => collect($items->items())->map(fn (FinanceItem $item) => $this->option($item, $productTypeUnspsc))->all(),
            'pagination' => ['more' => $items->hasMorePages()],
            'page' => $items->currentPage(),
            'page_count' => $items->lastPage(),
        ];
    }

    public function option(FinanceItem $item, ?string $productTypeUnspsc = null): array
    {
        $matches = $productTypeUnspsc !== null && $item->unspsc_code === $productTypeUnspsc;

        return [
            'id' => $item->gov_code,
            'text' => $item->label(),
            'path' => $item->path(),
            'matches_unspsc' => $matches,
            'tag' => $matches ? __('classification::finance.matches_unspsc') : null,
        ];
    }
}
