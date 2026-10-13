<?php

/*
|--------------------------------------------------------------------------
| GovStore Navigation — groups
|--------------------------------------------------------------------------
|
| The information architecture lives here, not in the packages. Packages register
| leaves with 'group' => '<id>'; a group renders only when at least one of its leaves
| is visible to the user, and a group with a single visible leaf renders as that leaf.
|
| Zones (attached to Snipe-IT's layouts/default by NavigationHook):
|   work   – sidebar, after Snipe-IT's Dashboard and before Assets (daily gov work)
|   admin  – sidebar, after Snipe-IT's Settings and Reports (configuration)
|   user   – top-right user menu, before Logout (personal settings)
|   context – navbar, left, after the logo (the working-office chip)
|   actions – navbar "＋ New" menu, in place of Snipe-IT's "Create New" (gov verbs:
|             receive, issue, request). Items with 'form' => [...] submit a POST.
|   topbar – navbar, before the user menu (inbox, basket)
|   content, scripts, body-end – page slots for package hooks (no menu markup)
|
| Order guide: frequency and audience — my work, my queues, operations, then admin.
| Keep every group at seven items or fewer.
*/

return [

    'groups' => [
        // Work zone
        'gov-workspace' => ['zone' => 'work', 'order' => 10, 'label' => 'tenantops::nav.workspace', 'icon' => 'fas fa-user-check'],
        'gov-queues' => ['zone' => 'work', 'order' => 20, 'label' => 'tenantops::nav.queues', 'icon' => 'fas fa-inbox'],
        'gov-store-ops' => ['zone' => 'work', 'order' => 30, 'label' => 'tenantops::nav.store', 'icon' => 'fas fa-warehouse'],
        'gov-catalogue' => ['zone' => 'work', 'order' => 40, 'label' => 'tenantops::nav.catalogue', 'icon' => 'fas fa-book-open'],
        'gov-committees' => ['zone' => 'work', 'order' => 50, 'label' => 'tenantops::nav.committees', 'icon' => 'fas fa-users'],
        'gov-tracking' => ['zone' => 'work', 'order' => 60, 'label' => 'tenantops::nav.tracking', 'icon' => 'fas fa-map-signs'],

        // Admin zone
        'gov-offices' => ['zone' => 'admin', 'order' => 10, 'label' => 'tenantops::nav.offices', 'icon' => 'fas fa-sitemap'],
        'gov-rules' => ['zone' => 'admin', 'order' => 20, 'label' => 'tenantops::nav.rules', 'icon' => 'fas fa-sliders-h'],
        'gov-catalogue-master' => ['zone' => 'admin', 'order' => 30, 'label' => 'tenantops::nav.catalogue_master', 'icon' => 'fas fa-boxes'],
        'gov-access-security' => ['zone' => 'admin', 'order' => 40, 'label' => 'tenantops::nav.access', 'icon' => 'fas fa-user-shield'],
        'gov-system' => ['zone' => 'admin', 'order' => 90, 'label' => 'tenantops::nav.system', 'icon' => 'fas fa-toolbox'],
    ],

    'max_group_size' => 7,

    /*
    | Snipe-IT navbar adjustments, applied by NavigationHook at compile time but decided
    | here at render time (no view:clear needed to flip them).
    */
    'topbar' => [
        // Snipe-IT's "Create New" bypasses the store ledger; stock enters through a goods receipt.
        'replace_core_create' => env('GOVSTORE_NAV_REPLACE_CORE_CREATE', true),
        // Icon-only Assets / Licenses / … / Users shortcuts duplicate the sidebar.
        'hide_core_shortcuts' => env('GOVSTORE_NAV_HIDE_CORE_SHORTCUTS', true),
        // Floating navbar (sticky on desktop, hide-on-scroll on phones).
        'sticky' => env('GOVSTORE_NAV_STICKY_HEADER', true),
    ],

    'user_menu' => [
        // Snipe-IT's own "Requested assets" — replaced by the gov request flow (My requests).
        'hide_core_requested' => env('GOVSTORE_NAV_HIDE_CORE_REQUESTED', true),
    ],

];
