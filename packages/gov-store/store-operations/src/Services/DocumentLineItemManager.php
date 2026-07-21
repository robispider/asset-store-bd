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
            $type = strtolower(class_basename($line['type']));
            $id = (int) $line['id'];
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