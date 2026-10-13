<?php

/*
|--------------------------------------------------------------------------
| GovStore stock intake (merged as `govstore-intake`)
|--------------------------------------------------------------------------
|
| In `grn_only` mode new stock enters the store through a posted Goods Receipt (GRN) only.
| EnforceGrnIntake guards Snipe-IT's own create paths by route name, so the rule also applies
| to super users (core's Gate::before grants them every ability, so a gate cannot).
| See docs/plans/topbar-navigation-plan.md §4.6.
|
|   Assets       – created by GRN posting from an asset model; direct create/store/clone and
|                  POST /api/v1/hardware are rejected.
|   Consumables,
|   accessories  – the stock record (the "stock card") is still created natively, because a GRN
|                  posts into an existing record; it must start at quantity 0.
|   Components   – unchanged: Snipe-IT requires qty >= 1 and LedgerStockGuard already blocks
|                  native quantity in ledger-opened offices.
|   Licenses     – GRN cannot receive licenses yet. `block_licenses` turns on the same block for
|                  them; switch it on together with license receipt so there is always a way in.
|   Import       – stock imports are a one-time migration path: super users only, and only into
|                  offices whose store ledger is not opened yet (`govstore:ledger-open`).
*/

return [

    // grn_only | open
    'mode' => env('GOVSTORE_INTAKE_MODE', 'grn_only'),

    'block_licenses' => env('GOVSTORE_INTAKE_BLOCK_LICENSES', false),

    'routes' => [
        'assets' => ['hardware.create', 'hardware.store', 'clone/hardware', 'api.assets.store'],
        'licenses' => ['licenses.create', 'licenses.store', 'clone/license', 'api.licenses.store'],
    ],

    // Stock-record creation that must start at quantity 0.
    'zero_quantity_routes' => ['consumables.store', 'accessories.store', 'api.consumables.store', 'api.accessories.store'],

    'import_route' => 'api.imports.importFile',

    'stock_import_types' => ['asset', 'license', 'consumable', 'accessory', 'component'],

];
