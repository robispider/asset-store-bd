<?php

// Single source for badge and stepper semantics: status → token tone + Font Awesome icon.
// Labels are translated through gs-theme::appearance.status.<key>.
return [
    // Documents
    'draft' => ['tone' => 'doc-draft', 'icon' => 'fa-pen'],
    'ready' => ['tone' => 'doc-ready', 'icon' => 'fa-circle-check'],
    'posted' => ['tone' => 'doc-posted', 'icon' => 'fa-lock'],
    'cancelled' => ['tone' => 'doc-cancelled', 'icon' => 'fa-ban'],
    'approved' => ['tone' => 'doc-approved', 'icon' => 'fa-thumbs-up'],
    'rejected' => ['tone' => 'doc-rejected', 'icon' => 'fa-circle-xmark'],
    'pending_approval' => ['tone' => 'status-pending', 'icon' => 'fa-hourglass-half'],
    'submitted' => ['tone' => 'doc-ready', 'icon' => 'fa-paper-plane'],

    // Assets
    'deployed' => ['tone' => 'status-deployed', 'icon' => 'fa-user'],
    'deployable' => ['tone' => 'status-ready', 'icon' => 'fa-check'],
    'pending' => ['tone' => 'status-pending', 'icon' => 'fa-clock'],
    'undeployable' => ['tone' => 'status-undeployable', 'icon' => 'fa-triangle-exclamation'],
    'archived' => ['tone' => 'status-archived', 'icon' => 'fa-box-archive'],

    // Generic feedback tones
    'success' => ['tone' => 'success', 'icon' => 'fa-circle-check'],
    'warning' => ['tone' => 'warning', 'icon' => 'fa-triangle-exclamation'],
    'danger' => ['tone' => 'danger', 'icon' => 'fa-circle-exclamation'],
    'info' => ['tone' => 'info', 'icon' => 'fa-circle-info'],

    // Office lifecycle (keys are lifecycle_status values; labels say what the office needs next)
    'office_unconfigured' => ['tone' => 'status-archived', 'icon' => 'fa-map-location-dot'],
    'office_provisioned' => ['tone' => 'status-pending', 'icon' => 'fa-user-plus'],
    'office_configured' => ['tone' => 'info', 'icon' => 'fa-list-check'],
    'office_operational' => ['tone' => 'status-ready', 'icon' => 'fa-circle-check'],
    'office_suspended' => ['tone' => 'status-undeployable', 'icon' => 'fa-circle-pause'],

    // Office membership (keys are membership_<status>; a released home office is "released_home")
    'membership_pending' => ['tone' => 'status-pending', 'icon' => 'fa-hourglass-half'],
    'membership_active' => ['tone' => 'status-ready', 'icon' => 'fa-circle-check'],
    'membership_release_requested' => ['tone' => 'warning', 'icon' => 'fa-door-open'],
    'membership_released_home' => ['tone' => 'info', 'icon' => 'fa-right-left'],
    'membership_released' => ['tone' => 'status-archived', 'icon' => 'fa-minus'],
    'membership_rejected' => ['tone' => 'doc-rejected', 'icon' => 'fa-circle-xmark'],
    'membership_suspended' => ['tone' => 'status-undeployable', 'icon' => 'fa-circle-pause'],
    'membership_inactive' => ['tone' => 'status-archived', 'icon' => 'fa-minus'],
    'membership_cancelled' => ['tone' => 'status-archived', 'icon' => 'fa-ban'],

    // Requests: one status derived from approval, fulfilment, receipt and return (Request::statusKey()).
    'request_pending_primary' => ['tone' => 'status-pending', 'icon' => 'fa-hourglass-half'],
    'request_pending_final' => ['tone' => 'status-pending', 'icon' => 'fa-hourglass-end'],
    'request_approved' => ['tone' => 'info', 'icon' => 'fa-warehouse'],
    'request_partly_issued' => ['tone' => 'info', 'icon' => 'fa-truck'],
    'request_issued' => ['tone' => 'status-ready', 'icon' => 'fa-hand-holding'],
    'request_received' => ['tone' => 'success', 'icon' => 'fa-circle-check'],
    'request_closed_short' => ['tone' => 'status-undeployable', 'icon' => 'fa-circle-minus'],
    'request_rejected' => ['tone' => 'doc-rejected', 'icon' => 'fa-circle-xmark'],
    'request_withdrawn' => ['tone' => 'status-archived', 'icon' => 'fa-rotate-left'],
    'request_return_requested' => ['tone' => 'warning', 'icon' => 'fa-arrow-rotate-left'],
    'request_return_drafted' => ['tone' => 'info', 'icon' => 'fa-file-import'],
    'request_needs_review' => ['tone' => 'status-undeployable', 'icon' => 'fa-triangle-exclamation'],
];
