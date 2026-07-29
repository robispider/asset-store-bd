<?php

namespace GovStore\Metadata\Support;

class LogicalField
{
    public string $identifier;   // e.g., 'govstore.baseline.grn'
    public string $displayName;  // e.g., 'GRN'
    public string $type;         // e.g., 'text'
    public ?string $helpText;
    public bool $required;

    public function __construct(
        string $identifier,
        string $displayName,
        string $type = 'text',
        ?string $helpText = null,
        bool $required = false
    ) {
        $this->identifier = $identifier;
        $this->displayName = $displayName;
        $this->type = $type;
        $this->helpText = $helpText;
        $this->required = $required;
    }
}
