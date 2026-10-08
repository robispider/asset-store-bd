<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use Illuminate\Support\Facades\Validator;

class RequireWarrantyCapability implements CapabilityInterface
{
    public function getRequirements(array $config = []): array
    {
        return [
            [
                'key'   => 'warranty_months',
                'type'  => 'integer',
                'rules' => 'required|integer|min:0'
            ]
        ];
    }

    public function validate(array $data, array $config = []): array
    {
        $validator = Validator::make($data, [
            'meta' => 'required|array',
            'meta.*.warranty_months' => 'required|integer|min:0|max:1200',
        ]);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    public function execute(object $item, array $config = []): void
    {
        // Executed during final ledger commit checks
    }

    public function renderUI(object $item = null, array $config = []): string
    {
        return view('storeops::capabilities.warranty', compact('item', 'config'))->render();
    }
}
