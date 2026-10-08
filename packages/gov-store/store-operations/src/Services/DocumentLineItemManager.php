<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Factories\StockableFactory;
use Exception;

class DocumentLineItemManager
{
    /**
     * Normalizes an array of raw item inputs, merging duplicates automatically.
     */
    public function processLines(array $rawLines, string $direction = 'IN'): array
    {
        $merged = [];

        foreach ($rawLines as $line) {
            // Normalize to short key if full namespace string is passed
            $stockType = \GovStore\StoreOperations\Enums\StockableType::fromString($line['type']);
            $type = strtolower(class_basename($stockType->value));
            $id = (int) $line['id'];
            $product = $stockType->value::query()->findOrFail($id);
            $context = app(\GovStore\TenantScope\Contexts\TenantContext::class);
            if ($stockType !== \GovStore\StoreOperations\Enums\StockableType::ASSET_MODEL) {
                abort_unless($context->locationId && (int) $product->location_id === $context->locationId
                    && (! $context->companyId || (int) $product->company_id === $context->companyId), 404);
            }
            if (! is_numeric($line['qty']) || (float) $line['qty'] !== (float) (int) $line['qty'] || (int) $line['qty'] < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => __('storeops::storeops.integer_quantity_required')]);
            }
            $qty = (int) $line['qty'];
            $cost = $line['unit_cost'] ?? 0.0;

            if ($qty <= 0) {
                continue; 
            }

            $key = "{$type}_{$id}";

            // Auto-merge duplicates
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $qty;
                $merged[$key]['unit_cost'] = max($merged[$key]['unit_cost'], $cost);
            } else {
                $merged[$key] = [
                    'product_type' => $type, // Must match the actual database column name
                    'product_id'   => $id,   // Must match the actual database column name
                    'quantity'     => $qty,
                    'unit_cost'    => $cost,
                ];
            }
        }

        // Domain validation: Prevent negative stock out projection
        if ($direction === 'OUT') {
            foreach ($merged as $item) {
                $adapter = StockableFactory::make($item['product_type'], $item['product_id']);
                $available = $adapter->getCurrentQuantity();

                if ($available < $item['quantity']) {
                    throw new Exception("Insufficient stock for {$adapter->getDisplayName()}. Available: {$available}, Requested: {$item['quantity']}.");
                }
            }
        }

        return array_values($merged);
    }
}
