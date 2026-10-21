@extends('layouts/default')
@section('title', __('storeops::rules.builder'))

@section('content')
@php
    $layerName = function (string $key) {
        return __('storeops::studio.layer.'.$key);
    };
    $status = $policy->status instanceof \BackedEnum ? $policy->status->value : (string) $policy->status;
    $statusTone = ['DRAFT' => 'warning', 'PUBLISHED' => 'success', 'ARCHIVED' => 'secondary'][$status] ?? 'secondary';
    $anchorName = $attachedTo->first()['name'] ?? null;
    $editorText = [
        'follows' => __('storeops::studio.note_follows'),
        'nobody' => __('storeops::studio.note_nobody'),
        'repeats' => __('storeops::studio.note_repeats'),
        'overrides' => __('storeops::studio.note_overrides'),
        'already_off' => __('storeops::studio.note_already_off'),
        'off_unset' => __('storeops::studio.note_off_unset'),
        'turns_back_on' => __('storeops::studio.note_turns_back_on'),
        'adds' => __('storeops::studio.note_adds'),
        'months' => __('storeops::studio.months'),
        'summary' => __('storeops::studio.editor_summary'),
        'warn' => __('storeops::studio.editor_warn'),
        'no_warn' => __('storeops::studio.editor_no_warn'),
    ];
@endphp
<div class="storeops-theme rst" data-rst-editor>
    <x-gs::page-header :title="$policy->name" :subtitle="__('storeops::studio.editor_lede')">
        <x-slot:meta>
            <span class="gs-tag gs-tag--{{ $statusTone }}">{{ __('storeops::studio.status.'.$status) }}</span>
            <span class="gs-tag gs-tag--outline">v{{ $policy->version ?? '1.0' }}</span>
            @if ($attachedCount)
                <span class="rst-muted">{{ trans_choice('storeops::studio.attached_to', $attachedCount, ['level' => $layerName($anchorKey), 'name' => $anchorName, 'count' => $attachedCount - 1]) }}</span>
            @else
                <span class="rst-muted">{{ __('storeops::studio.not_attached') }}</span>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <a class="gs-btn" href="{{ route('storeops.admin.rules.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ __('storeops::studio.back_to_rules') }}</a>
        </x-slot:actions>
    </x-gs::page-header>

    @if (session('success'))
        <x-gs::alert tone="success" dismissible>{{ session('success') }}</x-gs::alert>
    @endif
    @if (session('error') || $errors->any())
        <x-gs::alert tone="danger">{{ session('error') ?? $errors->first() }}</x-gs::alert>
    @endif
    @if ($policy->published_by)
        <x-gs::alert tone="info">{{ __('tenantops::access.published') }}: {{ $policy->published_by }} — {{ $policy->published_at }} — {{ $policy->publish_reason }}</x-gs::alert>
    @endif
    @if (! $editable)
        <x-gs::alert tone="info" :title="__('storeops::studio.read_only_title')">
            {{ $status === 'DRAFT' ? __('storeops::studio.read_only_permission') : __('storeops::studio.read_only_published') }}
            @if ($status !== 'DRAFT' && $canPublish)
                <form method="POST" action="{{ route('storeops.admin.rules.policies.duplicate', $policy->id) }}" class="rst-inline-form">
                    @csrf
                    <button type="submit" class="gs-btn gs-btn--sm gs-btn--primary">{{ __('storeops::studio.make_draft') }}</button>
                </form>
            @endif
        </x-gs::alert>
    @endif

    <div class="rst-layout">
        <aside class="rst-side rst-panel" aria-labelledby="rst-sits-title">
            <div class="rst-ph">
                <h2 id="rst-sits-title">{{ __('storeops::studio.where_sits') }}</h2>
            </div>
            <p class="rst-context rst-muted rst-small">{{ __('storeops::studio.where_sits_hint') }}</p>
            <ol class="rst-chain">
                @foreach ($chain as $layer)
                    @php
                        $silent = ! $layer['profile'] && $layer['key'] !== 'DEFAULT' && $layer['role'] !== 'this';
                    @endphp
                    <li @class(['rst-ch', 'rst-ch--none' => $silent, 'rst-ch--this' => $layer['role'] === 'this', 'rst-ch--after' => $layer['role'] === 'after'])>
                        <span class="rst-ch__dot" aria-hidden="true"></span>
                        <span>
                            <span class="rst-ch__lv">
                                @if ($layer['role'] === 'this')
                                    {{ __('storeops::studio.this_policy') }} · {{ $layerName($layer['key']) }}
                                @elseif ($layer['role'] === 'after')
                                    {{ __('storeops::studio.applies_after') }} · {{ $layerName($layer['key']) }}
                                @else
                                    {{ $layerName($layer['key']) }}
                                @endif
                            </span>
                            <span class="rst-ch__name">
                                @if ($layer['role'] === 'this')
                                    {{ $policy->name }}
                                @elseif ($layer['key'] === 'DEFAULT')
                                    {{ __('storeops::studio.package_default') }}
                                @else
                                    {{ $layer['profile']['name'] ?? ($layer['chosen'] ? __('storeops::studio.no_own_policy') : __('storeops::studio.not_chosen')) }}
                                @endif
                            </span>
                            <span class="rst-ch__sub">
                                @if ($layer['role'] === 'this')
                                    {{ trans_choice('storeops::studio.changes_from_above', $changes, ['count' => $changes]) }}
                                @elseif ($layer['role'] === 'after')
                                    {{ __('storeops::studio.after_hint') }}
                                @else
                                    {{ $layer['key'] === 'DEFAULT' ? __('storeops::studio.installed_packages') : ($layer['target'] ?? '—') }}
                                    @if ($layer['rules']) · {{ trans_choice('storeops::studio.sets_n', count($layer['rules']), ['count' => count($layer['rules'])]) }}@endif
                                @endif
                            </span>
                        </span>
                    </li>
                @endforeach
            </ol>
            @if ($officeMatters)
                <form class="rst-office" method="GET" action="{{ route('storeops.admin.rules.policies.edit', $policy->id) }}" data-rst-context>
                    <label for="rst-editor-office" class="rst-small rst-muted">{{ __('storeops::studio.preview_office') }}</label>
                    <select id="rst-editor-office" name="office" class="rst-field-select">
                        <option value="">{{ __('storeops::studio.no_office') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected($office?->id === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="gs-btn gs-btn--sm" data-rst-submit>{{ __('storeops::studio.show') }}</button>
                </form>
            @endif
            @if ($attachedCount)
                <div class="rst-office">
                    <span class="rst-small rst-muted">{{ __('storeops::studio.attached_list') }}</span>
                    <ul class="rst-eff">
                        @foreach ($attachedTo as $target)
                            <li><span>{{ $target['name'] }}</span><span class="rst-muted rst-small">{{ $layerName($target['layer']) }}</span></li>
                        @endforeach
                    </ul>
                    @if ($attachedCount > $attachedTo->count())
                        <span class="rst-small rst-muted">{{ __('storeops::studio.and_more', ['count' => $attachedCount - $attachedTo->count()]) }}</span>
                    @endif
                </div>
            @endif
        </aside>

        <form class="rst-main" action="{{ route('storeops.admin.rules.policies.draft', $policy->id) }}" method="POST" id="rst-editor-form">
            @csrf
            <section class="rst-panel" aria-label="{{ __('storeops::studio.rules_label') }}">
                <div class="rst-ed-cols" aria-hidden="true">
                    <span>{{ __('storeops::studio.rule') }}</span>
                    <span>{{ __('storeops::studio.coming_from_above') }}</span>
                    <span>{{ __('storeops::studio.your_choice') }}</span>
                    <span>{{ __('storeops::studio.storekeeper_gets') }}</span>
                </div>
                @foreach ($groupedRules as $groupName => $rules)
                    <div class="rst-ed-group">{{ __('storeops::rules.'.strtolower(str_replace(' ', '_', $groupName))) }}</div>
                    @foreach ($rules as $code => $row)
                        <div @class(['rst-ed-row', 'rst-ed-row--changed' => $row['differs'], 'rst-ed-row--mine' => $row['behavior'] !== 'INHERIT'])
                            data-rst-row data-above="{{ $row['above'] }}" data-above-label="{{ $row['above_label'] }}"
                            data-above-source="{{ $row['above_source'] }}" data-on-label="{{ $row['on_label'] }}" data-off-label="{{ __('storeops::studio.off') }}" data-none-label="{{ str_starts_with($code, 'require_') ? __('storeops::studio.not_required') : __('storeops::studio.not_on') }}"
                            data-above-months="{{ $row['above_config']['warranty_months'] ?? '' }}">
                            <div>
                                <span class="rst-row__name" id="rst-rule-{{ $code }}">{{ $row['name'] }}</span>
                                <span class="rst-row__desc">{{ $row['desc'] }}</span>
                                <span class="rst-ed-row__mine">● {{ __('storeops::studio.set_by_this_policy') }}</span>
                            </div>
                            <div class="rst-ed-above">
                                <span class="rst-st rst-st--{{ ['ENFORCE' => 'on', 'DISABLE' => 'off'][$row['above']] ?? 'none' }}">{{ $row['above'] ? $row['above_label'] : __('storeops::studio.not_set_above') }}</span>
                                <small>{{ $row['above_source'] ? __('storeops::studio.from_source', ['source' => $row['above_source']]) : __('storeops::studio.nobody_above') }}</small>
                            </div>
                            <div>
                                <div class="rst-seg" role="radiogroup" aria-labelledby="rst-rule-{{ $code }}">
                                    @foreach (['INHERIT' => __('storeops::studio.choice_keep'), 'ENFORCE' => str_starts_with($code, 'require_') ? __('storeops::studio.choice_require') : __('storeops::studio.choice_on'), 'DISABLE' => __('storeops::studio.choice_off')] as $value => $label)
                                        <label>
                                            <input type="radio" name="rules[{{ $code }}][behavior]" value="{{ $value }}" @checked($row['behavior'] === $value) @disabled(! $editable) data-rst-choice>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @if ($code === 'require_warranty')
                                    <label @class(['rst-cfg', 'active' => $row['behavior'] === 'ENFORCE']) data-rst-config>
                                        {{ __('storeops::storeops.rules_ui.default_warranty_period_months') }}
                                        <input type="number" name="rules[{{ $code }}][config][warranty_months]" value="{{ $row['config']['warranty_months'] ?? ($row['above_config']['warranty_months'] ?? 12) }}" min="0" @disabled(! $editable) data-rst-months>
                                    </label>
                                @endif
                            </div>
                            <div class="rst-ed-res" aria-live="polite">
                                <span class="rst-st rst-st--{{ ['ENFORCE' => 'on', 'DISABLE' => 'off'][$row['result']] ?? 'none' }}" data-rst-result>{{ $row['result_label'] }}</span>
                                <span class="rst-note rst-note--{{ $row['tone'] }}" data-rst-note>{{ $row['note'] }}</span>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </section>

            <div class="rst-foot">
                <div class="rst-foot__sum" aria-live="polite">
                    <strong data-rst-summary>{{ str_replace(':count', $changes, $editorText['summary']) }}</strong>
                    <span class="rst-muted rst-small" data-rst-warn>{{ $warnings ? str_replace(':count', $warnings, $editorText['warn']) : $editorText['no_warn'] }}</span>
                </div>
                <div class="rst-foot__acts">
                    <a href="{{ route('storeops.admin.rules.index') }}" class="gs-btn">{{ __('storeops::storeops.rules_ui.cancel') }}</a>
                    @if ($editable)
                        <button type="submit" class="gs-btn"><i class="fas fa-save" aria-hidden="true"></i> {{ __('storeops::storeops.rules_ui.save_draft') }}</button>
                        <button type="button" class="gs-btn gs-btn--primary" id="btn_trigger_publish"><i class="fas fa-rocket" aria-hidden="true"></i> {{ __('storeops::storeops.rules_ui.validate_publish') }}</button>
                    @endif
                </div>
            </div>
        </form>
    </div>
    <script type="application/json" id="rst-editor-text">@json($editorText)</script>

    <!-- IMPACT ANALYSIS & PUBLISHING MODAL -->
    <div class="modal fade" id="publishModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content storeops-inline-34">
                <div class="modal-header bg-primary storeops-inline-33">
                    <h4 class="modal-title storeops-inline-32"><i class="fa fa-warning"></i> {{ __('storeops::storeops.rules_ui.confirm_policy_publication') }}</h4>
                </div>
                <div class="modal-body storeops-inline-31">
                    <p class="lead storeops-inline-30">{{ __('storeops::storeops.rules_ui.you_are_about_to_promote_this_draft_to_the_active_live_standard') }}</p>
                    <div class="well storeops-inline-29">
                        <h5 class="storeops-inline-28">{{ __('storeops::storeops.rules_ui.estimated_blast_radius') }}</h5>
                        <p class="storeops-inline-27">🎯 <strong><span id="impact_categories">0</span> {{ __('storeops::storeops.rules_ui.product_categories') }}</strong> {{ __('storeops::storeops.rules_ui.will_be_affected') }}</p>
                        <p class="storeops-inline-26">📄 <strong><span id="impact_drafts">0</span> {{ __('storeops::storeops.rules_ui.open_draft_receipts') }}</strong> {{ __('storeops::storeops.rules_ui.currently_contain_these_items') }}</p>
                    </div>
                    <div class="alert storeops-inline-25" id="risk_alert_panel">
                        <i class="fa fa-info-circle"></i> <strong>{{ __('storeops::storeops.rules_ui.operation_warning') }}</strong><br>
                        <span id="risk_desc"></span>
                    </div>
                    <p class="text-danger storeops-inline-24"><i class="fa fa-shield"></i> <strong>{{ __('storeops::storeops.rules_ui.audit_integrity_assurance') }}</strong> {{ __('storeops::storeops.rules_ui.historically_posted_documents_are_completely_safe_and_will_not_b') }}</p>
                </div>
                <div class="modal-footer storeops-inline-23">
                    <form action="{{ route('storeops.admin.rules.policies.publish', $policy->id) }}" method="POST">
                        @csrf
                        <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('storeops::storeops.rules_ui.cancel') }}</button>
                        <button type="submit" class="btn btn-primary storeops-inline-22" id="btn_confirm_publish">{{ __('storeops::storeops.rules_ui.publish_apply_rules') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
@include('storeops::admin.rules.partials.client-config', ['rulesPage' => 'edit'])
@endsection
