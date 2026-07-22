<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use Illuminate\Support\Facades\Validator;

class RequireSerialCapability implements CapabilityInterface
{
    public function getRequirements(array $config = []): array
    {
        return ['serial_number'];
    }

    public function validate(array $data, array $config = []): array
    {
        // $data represents the specific metadata for ONE line item row
        // Expecting structure: ['meta' => [ 0 => ['serial_number' => 'XYZ'], 1 => ['serial_number' => 'ABC'] ]]
        
        $validator = Validator::make($data, [
            'meta' => 'required|array',
            'meta.*.serial_number' => 'required|string|distinct'
        ], [
            'meta.*.serial_number.required' => 'A serial number is missing.',
            'meta.*.serial_number.distinct' => 'Duplicate serial numbers are not allowed.'
        ]);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    public function execute(object $item, array $config = []): void
    {
        // Execution (Asset Creation) happens in CreateAssetsCapability
    }

    public function renderUI(object $item = null, array $config = []): string
    {
        // Delegate rendering entirely to the Blade partial!
        return view('storeops::capabilities.serial', [
            'item' => $item,
            'config' => $config
        ])->render();
    }
}