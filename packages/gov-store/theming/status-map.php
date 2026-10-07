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
];
