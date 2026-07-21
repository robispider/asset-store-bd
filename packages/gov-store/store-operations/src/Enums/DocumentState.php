<?php

namespace GovStore\StoreOperations\Enums;

enum DocumentState: string
{
    case DRAFT = 'DRAFT';       // Editable, being built by the storekeeper
    case READY = 'READY';       // Finished editing, waiting for physical/committee verification
    case POSTED = 'POSTED';     // Finalized, written to the Inventory Ledger (Immutable)
    case CANCELLED = 'CANCELLED'; // Voided before posting
}