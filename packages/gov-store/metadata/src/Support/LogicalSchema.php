<?php

namespace GovStore\Metadata\Support;

class LogicalSchema
{
    /**
     * @var \GovStore\Metadata\Support\LogicalField[]
     */
    protected array $fields = [];

    /**
     * Appends a logical field definition, automatically deduplicating by identifier.
     */
    public function addField(LogicalField $field): self
    {
        $this->fields[$field->identifier] = $field;
        return $this;
    }

    /**
     * Bulk append multiple fields to the schema.
     *
     * @param \GovStore\Metadata\Support\LogicalField[] $fields
     */
    public function addFields(array $fields): self
    {
        foreach ($fields as $field) {
            if ($field instanceof LogicalField) {
                $this->addField($field);
            }
        }
        return $this;
    }

    /**
     * Retrieve the deduplicated, flattened logical fields.
     *
     * @return \GovStore\Metadata\Support\LogicalField[]
     */
    public function getFields(): array
    {
        return array_values($this->fields);
    }
}
