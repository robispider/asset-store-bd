{{--
    Office settings: each lifecycle action is its own explained dialog (plan Phase 3).
    Expects $location, $profile; $geoTrail optional. Concurrency guards (expected_*) travel with every form.
--}}
@php
    $status = $profile->lifecycle_status;
    $active = in_array($status, \GovStore\Organization\Services\OfficeLifecycleService::ACTIVE, true);
    $bangla = str_starts_with(strtolower(app()->getLocale()), 'bn');
    $geo = $profile->geoArea;
    $currentArea = $geo ? ($bangla && $geo->bn_name ? $geo->bn_name : $geo->en_name) : null;
    $reopen = old('action') && $errors->hasAny(['reason', 'confirmation', 'geo_area_id', 'action']) ? old('action') : null;
    $dialogs = [];
    if ($status === 'suspended') {
        $dialogs['resume'] = ['title' => __('organization_labels::office.resume_dialog_title', ['name' => $location->name]),
            'effects' => [['ok', 'fa-circle-check', __('organization_labels::office.resume_effect')]],
            'ack' => __('organization_labels::office.ack_resume'), 'confirm' => __('organization_labels::office.resume_confirm'), 'tone' => 'primary'];
    } elseif ($active) {
        $dialogs['suspend'] = ['title' => __('organization_labels::office.pause_dialog_title', ['name' => $location->name]),
            'effects' => [
                ['stop', 'fa-circle-xmark', __('organization_labels::office.pause_effect_stop')],
                ['ok', 'fa-circle-check', __('organization_labels::office.pause_effect_continue')],
                ['info', 'fa-lock', __('organization_labels::office.pause_effect_lock')],
            ],
            'ack' => __('organization_labels::office.ack_pause'), 'confirm' => __('organization_labels::office.pause_confirm'), 'tone' => 'danger'];
    }
    if ($active || $status === 'suspended') {
        $dialogs['relocate'] = ['title' => __('organization_labels::office.move_dialog_title', ['name' => $location->name]),
            'effects' => [
                ['ok', 'fa-circle-check', __('organization_labels::office.move_effect_same')],
                ['info', 'fa-circle-info', __('organization_labels::office.move_effect_verify')],
                ['info', 'fa-map-location-dot', __('organization_labels::office.move_effect_scope')],
            ],
            'ack' => __('organization_labels::office.ack_move'), 'confirm' => __('organization_labels::office.move_confirm'), 'tone' => 'primary'];
    }
@endphp
<x-gs::box :title="__('organization_labels::office.section_settings')" id="govorg-settings">
    <p class="govorg-muted">{{ __('organization_labels::office.settings_hint') }}</p>
    @isset($dialogs['suspend'])
        <x-gs::action-row :title="__('organization_labels::office.pause_title')" :description="__('organization_labels::office.pause_desc')">
            <x-slot:actions><button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-toggle="modal" data-target="#govorg-dialog-suspend">{{ __('organization_labels::office.pause_button') }}</button></x-slot:actions>
        </x-gs::action-row>
    @endisset
    @isset($dialogs['resume'])
        <x-gs::action-row :title="__('organization_labels::office.resume_title')" :description="__('organization_labels::office.resume_desc')">
            <x-slot:actions><button type="button" class="gs-btn gs-btn--primary" data-toggle="modal" data-target="#govorg-dialog-resume">{{ __('organization_labels::office.resume_button') }}</button></x-slot:actions>
        </x-gs::action-row>
    @endisset
    @isset($dialogs['relocate'])
        <x-gs::action-row :title="__('organization_labels::office.move_title')" :description="__('organization_labels::office.move_desc')">
            <x-slot:actions><button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-toggle="modal" data-target="#govorg-dialog-relocate">{{ __('organization_labels::office.move_button') }}</button></x-slot:actions>
        </x-gs::action-row>
    @endisset
    <x-gs::action-row id="govorg-close" :title="__('organization_labels::office.close_title')" :unavailable="__('organization_labels::orglabel.lifecycle_clearance_pending')">
        <x-slot:actions><button type="button" class="gs-btn gs-btn--light gs-btn--outline" disabled aria-describedby="govorg-close-why">{{ __('organization_labels::office.close_button') }}</button></x-slot:actions>
    </x-gs::action-row>
</x-gs::box>

@foreach ($dialogs as $action => $dialog)
    <div class="modal fade" id="govorg-dialog-{{ $action }}" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="govorg-dialog-{{ $action }}-title" @if ($reopen === $action) data-govorg-reopen @endif>
        <div class="modal-dialog" role="document">
            <form class="modal-content" method="POST" action="{{ route('gov.org.hub.lifecycle', $location->id) }}">
                @csrf
                <input type="hidden" name="action" value="{{ $action }}">
                <input type="hidden" name="expected_status" value="{{ $profile->lifecycle_status }}">
                <input type="hidden" name="expected_geo_area_id" value="{{ $profile->geo_area_id }}">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('organization_labels::office.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h2 class="modal-title" id="govorg-dialog-{{ $action }}-title">{{ $dialog['title'] }}</h2>
                </div>
                <div class="modal-body">
                    @if ($action === 'relocate')
                        <dl class="gs-kv" style="--gs-kv-columns: 1">
                            <div class="gs-kv__item"><dt class="gs-kv__label">{{ __('organization_labels::office.move_now') }}</dt>
                                <dd class="gs-kv__value">{{ $currentArea ?? __('organization_labels::office.not_placed') }} @if (! empty($geoTrail))<x-gs::trail :items="$geoTrail" />@endif</dd></div>
                        </dl>
                        <x-gs::form-row for="lifecycle-geo" :label="__('organization_labels::office.move_to')" required
                            :error="$reopen === $action ? $errors->first('geo_area_id') : null">
                            <select class="form-control" id="lifecycle-geo" name="geo_area_id" style="width: 100%" required></select>
                        </x-gs::form-row>
                    @endif
                    <ul class="govorg-effects">
                        @foreach ($dialog['effects'] as [$kind, $icon, $text])
                            <li class="govorg-effect--{{ $kind }}"><i class="fas {{ $icon }}" aria-hidden="true"></i><span>{{ $text }}</span></li>
                        @endforeach
                    </ul>
                    <x-gs::form-row for="lifecycle-reason-{{ $action }}" :label="__('organization_labels::office.reason')" required
                        :help="__('organization_labels::office.reason_hint')" :error="$reopen === $action ? $errors->first('reason') : null">
                        <textarea class="form-control" id="lifecycle-reason-{{ $action }}" name="reason" rows="3" minlength="5" maxlength="1000" required aria-describedby="lifecycle-reason-{{ $action }}-help">{{ $reopen === $action ? old('reason') : '' }}</textarea>
                    </x-gs::form-row>
                    <label class="govorg-ack" for="lifecycle-ack-{{ $action }}">
                        <input type="checkbox" id="lifecycle-ack-{{ $action }}" name="confirmation" value="1" required>
                        <span>{{ $dialog['ack'] }}</span>
                    </label>
                    @if ($reopen === $action && $errors->has('confirmation'))
                        <p class="gs-form-row__error" role="alert">{{ $errors->first('confirmation') }}</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-dismiss="modal">{{ __('organization_labels::office.cancel') }}</button>
                    <button type="submit" class="gs-btn gs-btn--{{ $dialog['tone'] }}">{{ $dialog['confirm'] }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
