{{--
    The roles a person holds at one office, each with "Hand over…" or, while a colleague decides, who it is
    waiting for and a labelled Cancel button. Temporary cover can't be handed over; it ends on its own.
    Expects $office (id), $officeName, $officeRoles (slugs), $officeCover (grant rows), $outgoing, $user.
--}}
<div class="gs-roles govmem-roles">
    @foreach ($officeRoles as $slug)
        @php $pending = $outgoing->first(fn ($h) => (int) $h->location_id === (int) $office && $h->role_type === $slug); @endphp
        <x-gs::role-slot :role="__('office_membership::member.role_'.$slug)" :description="__('office_membership::member.role_desc_'.$slug)"
            :person="$user->present()->fullName"
            :meta="$pending
                ? __('office_membership::member.handover_waiting', ['name' => $pending->incomingUser?->present()->fullName ?? '', 'time' => $pending->created_at?->diffForHumans()])
                : __('office_membership::member.handover_yours', ['office' => $officeName])">
            <x-slot:actions>
                @if ($pending)
                    <form method="POST" action="{{ route('gov.membership.handshake.cancel', $pending->id) }}" class="govmem-inline-form">
                        @csrf
                        <x-gs::button type="submit" tone="light" outline size="sm">{{ __('office_membership::member.handover_cancel') }}</x-gs::button>
                    </form>
                @else
                    <button type="button" class="gs-btn gs-btn--light gs-btn--outline gs-btn--sm" data-govmem-handover
                        data-office="{{ $office }}" data-office-name="{{ $officeName }}" data-role="{{ $slug }}"
                        data-role-name="{{ __('office_membership::member.role_'.$slug) }}" data-role-desc="{{ __('office_membership::member.role_desc_'.$slug) }}">
                        {{ __('office_membership::member.handover_button') }}
                    </button>
                @endif
            </x-slot:actions>
        </x-gs::role-slot>
    @endforeach
    @foreach ($officeCover as $grant)
        <p class="govmem-muted govmem-small govmem-flush"><i class="fas fa-clock" aria-hidden="true"></i>
            {{ __('office_membership::member.cover_until', ['role' => __('office_membership::member.role_'.$grant->role_slug),
                'office' => $officeName, 'date' => \Illuminate\Support\Carbon::parse($grant->expires_at)->translatedFormat('j M Y')]) }}</p>
    @endforeach
</div>
