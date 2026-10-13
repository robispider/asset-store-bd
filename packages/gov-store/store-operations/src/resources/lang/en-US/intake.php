<?php

/*
| GRN-only stock intake (config/intake.php, EnforceGrnIntake).
*/

return [
    'direct_create_blocked' => 'New stock is added through a goods receipt (GRN). Open Receipts & issues and start a new goods receipt.',
    'record_starts_at_zero' => 'A new stock item starts at quantity 0. Save it with quantity 0, then receive the stock through a goods receipt (GRN).',
    'import_super_only' => 'Only a super administrator can import stock, and only for an office whose store ledger is not opened yet.',
    'import_ledger_open' => '":item" belongs to an office whose store ledger is open. Receive that stock through a goods receipt instead. Nothing was imported.',
    'item_not_listed' => 'Item not listed?',
    'add_stock_item' => 'Add it to the office catalogue',
    'add_stock_item_help' => 'Opens a new tab. Save the new item with quantity 0, then search for it here.',
];
