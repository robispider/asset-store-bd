<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use Illuminate\Support\Facades\Validator;

class RequireSerialCapability implements CapabilityInterface
{
    public function getRequirements(array $config = []): array
    {
        return [
            [
                'key'   => 'serial_number',
                'type'  => 'string',
                'rules' => 'required'
            ]
        ];
    }

    public function validate(array $data, array $config = []): array
    {
        $validator = Validator::make($data, [
            'serial_number' => 'required|string|distinct'
        ]);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    public function execute(object $item, array $config = []): void
    {
        // Individual instantiation is handled downstream by CreateAssetsCapability
    }
}