<?php

namespace GovStore\StoreOperations\Contracts;

use GovStore\StoreOperations\Models\Document;

/**
 * Optional for capabilities: adds rows to the workspace's validation checklist.
 * Each row is ['label' => string, 'passed' => bool, 'level' => 'required'|'warning'].
 * A 'required' row that has not passed blocks posting; a 'warning' row never does.
 */
interface ContributesChecklist
{
    public function checklist(Document $document, object $item, array $config = []): array;
}
