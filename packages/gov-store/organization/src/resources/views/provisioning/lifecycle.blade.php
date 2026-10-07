<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">{{ __('organization_labels::orglabel.lifecycle_title') }}</h3></div>
    <div class="box-body">
        <p>{{ __('organization_labels::orglabel.lifecycle_help') }}</p>
        <p class="text-muted">{{ __('organization_labels::orglabel.lifecycle_clearance_pending') }}</p>
        <form method="POST" action="{{ route('gov.org.hub.lifecycle', $location->id) }}">
            @csrf
            <input type="hidden" name="expected_status" value="{{ $profile->lifecycle_status }}">
            <input type="hidden" name="expected_geo_area_id" value="{{ $profile->geo_area_id }}">
            <div class="form-group">
                <label for="lifecycle-action">{{ __('organization_labels::orglabel.lifecycle_action') }}</label>
                <select class="form-control" id="lifecycle-action" name="action" required>
                    @if($profile->lifecycle_status === 'suspended')
                        <option value="resume">{{ __('organization_labels::orglabel.lifecycle_resume') }}</option>
                    @elseif(in_array($profile->lifecycle_status, ['provisioned', 'configured', 'operational'], true))
                        <option value="suspend">{{ __('organization_labels::orglabel.lifecycle_suspend') }}</option>
                    @endif
                    <option value="relocate">{{ __('organization_labels::orglabel.lifecycle_relocate') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label for="lifecycle-geo">{{ __('organization_labels::orglabel.lifecycle_new_geo') }}</label>
                <select class="form-control" id="lifecycle-geo" name="geo_area_id" style="width:100%"></select>
                <p class="help-block">{{ __('organization_labels::orglabel.lifecycle_geo_help') }}</p>
            </div>
            <div class="form-group">
                <label for="lifecycle-reason">{{ __('organization_labels::orglabel.lifecycle_reason') }}</label>
                <textarea class="form-control" id="lifecycle-reason" name="reason" minlength="5" maxlength="1000" required></textarea>
            </div>
            <div class="form-group">
                <label for="lifecycle-confirmation">{{ __('organization_labels::orglabel.lifecycle_confirmation') }}</label>
                <input class="form-control" id="lifecycle-confirmation" name="confirmation" pattern="CHANGE" autocomplete="off" required>
            </div>
            <button class="btn btn-warning" type="submit">{{ __('organization_labels::orglabel.lifecycle_apply') }}</button>
        </form>
        <hr>
        <h4>{{ __('organization_labels::orglabel.starter_title') }}</h4>
        <p>{{ __('organization_labels::orglabel.starter_status_'.($starter->status ?? 'waiting')) }}</p>
        @if($starter && $starter->failure_reference)
            <p>{{ __('organization_labels::orglabel.starter_reference') }}: {{ $starter->failure_reference }}</p>
        @endif
        @if($profile->office_admin_id && in_array($profile->lifecycle_status, ['provisioned', 'configured', 'operational'], true) && (!$starter || $starter->status !== 'completed'))
            <form method="POST" action="{{ route('gov.org.hub.retry-starter', $location->id) }}">
                @csrf
                <button class="btn btn-default" type="submit">{{ __('organization_labels::orglabel.starter_retry') }}</button>
            </form>
        @endif
    </div>
</div>
