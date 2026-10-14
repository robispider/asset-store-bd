{{--
    Personal code: 6 characters, works once, for 24 hours. The person gives it with their username to an
    office administrator, who adds them straight away. Expects $activeToken, $user.
--}}
<x-gs::box :title="__('office_membership::member.code_personal_title')" id="personal-code">
    @if ($activeToken)
        <p class="govmem-code" aria-label="{{ __('office_membership::member.code_personal_title') }}">
            <span class="govmem-mono">{{ trim(chunk_split($activeToken->token, 3, ' ')) }}</span>
        </p>
        <x-gs::key-value :columns="1" :items="[
            ['label' => __('office_membership::member.code_username'), 'value' => $user->username],
            ['label' => __('office_membership::member.code_works_until'), 'value' => __('office_membership::member.code_until_value', [
                'date' => $activeToken->expires_at->translatedFormat('j M, H:i'), 'left' => $activeToken->expires_at->diffForHumans(['parts' => 1])])],
            ['label' => __('office_membership::member.code_use'), 'value' => __('office_membership::member.code_once')],
        ]" />
        <ol class="govmem-steps">
            <li>{{ __('office_membership::member.code_step_give') }}</li>
            <li>{{ __('office_membership::member.code_step_added') }}</li>
            <li>{{ __('office_membership::member.code_step_appears') }}</li>
        </ol>
        <p class="govmem-muted govmem-small"><i class="fas fa-lock" aria-hidden="true"></i> {{ __('office_membership::member.code_personal_warning') }}</p>
        <form method="POST" action="{{ route('gov.membership.token.generate') }}">
            @csrf
            <x-gs::button type="submit" tone="light" outline size="sm" icon="fa-rotate">{{ __('office_membership::member.code_personal_new') }}</x-gs::button>
        </form>
    @else
        <p class="govmem-muted">{{ __('office_membership::member.code_personal_hint') }}</p>
        <form method="POST" action="{{ route('gov.membership.token.generate') }}">
            @csrf
            <x-gs::button type="submit" tone="light" outline icon="fa-key">{{ __('office_membership::member.code_personal_make') }}</x-gs::button>
        </form>
    @endif
</x-gs::box>
