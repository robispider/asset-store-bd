{{--
    The office readiness checklist, built once from OfficeReadinessService output for every screen.
    Expects: $readiness (checklist + users_count), $profile, $people (user id => name, optional),
    $roles (primary_approver_id, storekeeper_id; optional), $readonly (staff pages), $rolesAnchor.
--}}
@php
    $people = $people ?? [];
    $checklist = $readiness['checklist'] ?? [];
    $readonly = $readonly ?? false;
    $rolesAnchor = $rolesAnchor ?? '#govorg-roles';
    $staffAction = ! $readonly && \Illuminate\Support\Facades\Route::has('gov.membership.admin.index')
        ? ['label' => __('organization_labels::office.item_add_staff'), 'href' => route('gov.membership.admin.index')] : null;
    $choose = $readonly ? null : ['label' => __('organization_labels::office.item_choose'), 'href' => $rolesAnchor];
    $staffCount = (int) ($readiness['users_count'] ?? 0);
    $items = [
        ['label' => __('organization_labels::office.item_staff'), 'done' => (bool) ($checklist['has_users'] ?? false),
            'value' => trans_choice('organization_labels::office.item_staff_value', $staffCount, ['count' => $staffCount]), 'action' => $staffAction],
        ['label' => __('organization_labels::office.item_admin'), 'done' => (bool) ($checklist['has_office_admin'] ?? false),
            'value' => $profile?->officeAdmin?->present()->fullName, 'action' => $choose],
        ['label' => __('organization_labels::office.item_approver'), 'done' => (bool) ($checklist['has_primary_approver'] ?? false),
            'value' => $people[$roles->primary_approver_id ?? 0] ?? null, 'action' => $choose],
        ['label' => __('organization_labels::office.item_storekeeper'), 'done' => (bool) ($checklist['has_storekeeper'] ?? false),
            'value' => $people[$roles->storekeeper_id ?? 0] ?? null, 'action' => $choose],
    ];
    if (array_key_exists('has_approval_path', $checklist) && ! $checklist['has_approval_path']) {
        $items[] = ['label' => __('organization_labels::office.item_approval_path'), 'done' => false, 'action' => $choose];
    }
    $left = collect($items)->where('done', false)->count();
    $status = $profile->lifecycle_status ?? 'unconfigured';
    [$title, $caption] = match (true) {
        $status === 'suspended' => [__('organization_labels::office.readiness_paused'), __('organization_labels::office.readiness_paused_caption')],
        $status === 'operational' || $left === 0 => [__('organization_labels::office.readiness_live'), __('organization_labels::office.readiness_live_caption')],
        default => [trans_choice('organization_labels::office.readiness_left', $left, ['count' => $left]), __('organization_labels::office.readiness_caption')],
    };
@endphp
<x-gs::readiness :items="$items" :title="$title" :caption="$caption" :readonly="$readonly || $status === 'suspended'" />
