<?php

namespace GovStore\StoreOperations\Traits;

use GovStore\StoreOperations\Models\DocumentReference;

trait HasStoreReferences
{
    /**
     * Polymorphic relationship to document references 
     * (e.g., Challan, Nothi, Tender, Work Order)
     */
    public function references()
    {
        return $this->morphMany(DocumentReference::class, 'document');
    }

    public function addReference(string $type, string $number, ?string $date = null): void
    {
        $this->references()->create([
            'reference_type' => $type,
            'reference_number' => $number,
            'reference_date' => $date,
        ]);
    }
}