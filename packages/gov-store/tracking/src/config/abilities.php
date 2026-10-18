<?php

$read = ['tracking_member', 'tracking_admin'];
$head = ['tracking_head', 'tracking_admin'];
$edit = ['tracking_head', 'tracking_officer', 'tracking_admin'];
$support = [...$edit, 'tracking_support'];
$definitions = [
    'tracking.programmes.view' => $read, 'tracking.programmes.create' => ['tracking_admin'],
    'tracking.programmes.manage' => $head, 'tracking.codes.view' => $read,
    'tracking.codes.manage' => $edit, 'tracking.codes.activate' => $head, 'tracking.codes.archive' => $head,
    'tracking.team.view' => $read, 'tracking.team.manage' => $head,
    'tracking.documents.view' => $read, 'tracking.documents.manage' => $edit,
    'tracking.retrospective.view' => $support, 'tracking.retrospective.manage' => $support,
    'tracking.offices.view' => ['authenticated'], 'tracking.settings.manage' => ['superuser'],
    'tracking.execution.verify' => ['storekeeper', 'office_admin', 'company_admin', 'primary_approver', 'final_approver'],
    'tracking.codes.sheet' => [...$read, 'storekeeper', 'office_admin', 'company_admin', 'primary_approver', 'final_approver'],
];

$abilities = array_map(fn ($roles) => ['roles' => $roles, 'enforce' => true], $definitions);
$abilities['tracking.settings.manage']['national'] = true;

return $abilities;
