<?php

namespace GovStore\StoreOperations\Contracts;

interface CapabilityInterface
{
    /**
     * Return the specific input requirements exposed by this capability.
     * Maps the field key to its raw rule properties.
     * 
     * @param array $config Configuration payload saved at the profile level.
     * @return array E.g., ['serial_number' => ['type' => 'string', 'rules' => 'required']]
     */
    public function getRequirements(array $config = []): array;

    /**
     * Validate user input against the capability requirements.
     * 
     * @param array $data The raw user input values.
     * @param array $config Configuration payload saved at the profile level.
     * @return array Array of validation errors, or empty if successful.
     */
    public function validate(array $data, array $config = []): array;

    /**
     * Execute the physical transaction/materialization for a single document line.
     * 
     * @param object $item The Eloquent line item model.
     * @param array $config Configuration payload saved at the profile level.
     */
    public function execute(object $item, array $config = []): void;
}