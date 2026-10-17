{{-- The selected level and the policy attached to it, with attach / replace / detach for publishers. --}}
<div class="rst-attach rst-attach--ruled">
    <span>
        <span class="rst-muted rst-small">{{ __('storeops::studio.selected_level', ['level' => $layerName($node['layer'])]) }}</span>
        <strong>{{ $node['name'] }}</strong>
        —
        @if ($node['profile'])
            {{ __('storeops::studio.policy_attached', ['policy' => $node['profile']['name']]) }}
        @else
            <span class="rst-muted">{{ __('storeops::studio.no_policy_attached') }}</span>
        @endif
    </span>
    @if ($node['profile'])
        <a class="gs-btn gs-btn--sm" href="{{ route('storeops.admin.rules.policies.edit', $node['profile']['id']) }}"><i class="fas fa-pen" aria-hidden="true"></i> {{ __('storeops::studio.open_policy') }}</a>
    @endif
    @if ($canPublish)
        <form method="POST" action="{{ route('storeops.admin.rules.assign') }}">
            @csrf
            <input type="hidden" name="target_type" value="{{ $node['target_type'] }}">
            <input type="hidden" name="target_id" value="{{ $node['target_id'] }}">
            <label class="gs-sr-only" for="rst-attach-policy">{{ __('storeops::studio.choose_policy') }}</label>
            <select id="rst-attach-policy" name="profile_id" required>
                <option value="">{{ __('storeops::studio.choose_policy') }}</option>
                @foreach ($publishedProfiles as $profile)
                    <option value="{{ $profile->id }}">{{ $profile->name }} (v{{ $profile->version ?? '1.0' }})</option>
                @endforeach
            </select>
            <button type="submit" class="gs-btn gs-btn--sm">{{ $node['profile'] ? __('storeops::studio.replace') : __('storeops::studio.attach') }}</button>
        </form>
        @if ($node['assignment_id'])
            <form method="POST" action="{{ route('storeops.admin.rules.unassign', $node['assignment_id']) }}" class="storeops-unassign">
                @csrf
                <button type="submit" class="gs-btn gs-btn--sm gs-btn--ghost">{{ __('storeops::studio.detach') }}</button>
            </form>
        @endif
    @endif
</div>
