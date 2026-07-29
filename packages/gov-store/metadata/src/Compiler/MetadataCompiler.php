<?php

namespace GovStore\Metadata\Compiler;

use GovStore\Metadata\Support\LogicalSchema;
use GovStore\Metadata\Models\MetadataFieldMapping;
use App\Models\CustomField;
use App\Models\CustomFieldset;

class MetadataCompiler
{
    /**
     * Compiles a Logical Schema into a physical Snipe-IT CustomFieldset.
     */
    public function compile(string $fieldsetName, LogicalSchema $schema): CustomFieldset
    {
        // 1. Resolve or construct the target Custom Fieldset
        $fieldset = CustomFieldset::firstOrCreate(['name' => $fieldsetName]);

        $physicalSyncPayload = [];

        // 2. Loop through Logical Fields and ensure physical counterparts exist
        foreach ($schema->getFields() as $logicalField) {
            $field = $this->resolvePhysicalField($logicalField);

            $physicalSyncPayload[$field->id] = [
                'required' => $logicalField->required ? 1 : 0,
                'order' => 10,
            ];
        }

        // 3. Sync fields in the database
        $fieldset->fields()->sync($physicalSyncPayload);

        return $fieldset;
    }

    /**
     * Resolves a LogicalField to a core CustomField, building mapping connections.
     */
    protected function resolvePhysicalField($logicalField): CustomField
    {
        $mapping = MetadataFieldMapping::where('identifier', $logicalField->identifier)->first();
        $field = null;

        if ($mapping) {
            $field = CustomField::find($mapping->custom_field_id);
        }

        if (!$field) {
            $field = CustomField::where('name', $logicalField->displayName)->first();
        }

        if (!$field) {
            $field = new CustomField();
            $field->name = $logicalField->displayName;
            $field->element = $logicalField->type;
            $field->format = 'ANY';
            $field->help_text = $logicalField->helpText;
            $field->save();
        }

        if (!$mapping) {
            MetadataFieldMapping::create([
                'identifier' => $logicalField->identifier,
                'custom_field_id' => $field->id,
            ]);
        }

        return $field;
    }
}