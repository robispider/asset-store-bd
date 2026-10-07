@auth
    @if(config('govstore-access.enforcement_date'))
        <div class="alert alert-info" role="status">
            {{ trans('tenantops::access.rollout', ['date' => config('govstore-access.enforcement_date'), 'contact' => config('govstore-access.help_contact')], 'bn-BD') }}
            {{ trans('tenantops::access.rollout', ['date' => config('govstore-access.enforcement_date'), 'contact' => config('govstore-access.help_contact')], 'en-US') }}
        </div>
    @endif
@endauth
