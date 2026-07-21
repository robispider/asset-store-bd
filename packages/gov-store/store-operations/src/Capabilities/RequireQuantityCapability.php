<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use Illuminate\Support\Facades\Validator;

class RequireQuantityCapability implements CapabilityInterface
{
    public function getRequirements(array $config = []): array
    {
        // Quantity is stored directly on the main lines table, no custom EAV needed
        return []; 
    }

    public function validate(array $data, array $config = []): array
    {
        $validator = Validator::make($data, [
            'qty' => 'required|integer|min:1'
        ]);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    public function execute(object $item, array $config = []): void
    {
        // Executed natively during main document line processing
    }
}