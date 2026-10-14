{{--
    "Hand over a role" dialog. One colleague list per office is rendered on the server; the opening button
    (data-govmem-handover) picks the office and role. Lists for other offices are hidden and disabled so
    they never submit. Expects $colleagues (office id => people).
--}}
@php $reopen = old('form') === 'handover'; @endphp
<div class="modal fade" id="govmem-handover" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="govmem-handover-title"
    @if ($reopen) data-govmem-reopen data-office="{{ old('location_id') }}" data-role="{{ old('role_type') }}" @endif>
    <div class="modal-dialog" role="document">
        <form class="modal-content" method="POST" action="{{ route('gov.membership.handshake.propose') }}">
            @csrf
            <input type="hidden" name="form" value="handover">
            <input type="hidden" name="location_id" value="{{ old('location_id') }}" data-govmem-field="office">
            <input type="hidden" name="role_type" value="{{ old('role_type') }}" data-govmem-field="role">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('office_membership::member.close') }}"><span aria-hidden="true">&times;</span></button>
                <h2 class="modal-title" id="govmem-handover-title">{{ __('office_membership::member.handover_title') }} <span data-govmem-text="role-name"></span></h2>
            </div>
            <div class="modal-body govmem-stack">
                <p class="govmem-muted govmem-flush"><span data-govmem-text="office-name"></span>. <span data-govmem-text="role-desc"></span></p>
                @foreach ($colleagues as $office => $people)
                    <fieldset class="govmem-pick" data-govmem-office="{{ $office }}" hidden disabled>
                        <legend class="govmem-legend">{{ __('office_membership::member.handover_who') }}</legend>
                        @if (count($people) > 6)
                            <label class="sr-only" for="govmem-filter-{{ $office }}">{{ __('office_membership::member.handover_filter') }}</label>
                            <input type="search" class="form-control" id="govmem-filter-{{ $office }}" data-govmem-filter autocomplete="off"
                                placeholder="{{ __('office_membership::member.handover_filter') }}">
                        @endif
                        <ul class="govmem-people" role="list">
                            @foreach ($people as $person)
                                <li data-govmem-name="{{ mb_strtolower($person['name'].' '.$person['username']) }}">
                                    <label class="govmem-person @if ($person['leaving']) govmem-person--off @endif">
                                        <input type="radio" name="assigned_user_id" value="{{ $person['id'] }}" required
                                            @checked((string) old('assigned_user_id') === (string) $person['id']) @disabled($person['leaving'])>
                                        <x-gs::avatar :name="$person['name']" />
                                        <span class="govmem-person__text">
                                            <strong>{{ $person['name'] }}</strong>
                                            <span class="govmem-meta">
                                                {{ collect([$person['job'], $person['username']])->filter()->implode(' · ') }} ·
                                                @if ($person['leaving'])
                                                    {{ __('office_membership::member.handover_person_leaving') }}
                                                @elseif ($person['roles'])
                                                    {{ __('office_membership::member.handover_person_roles', ['roles' => implode(', ', $person['roles'])]) }}
                                                @else
                                                    {{ __('office_membership::member.handover_person_free') }}
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>
                @endforeach
                <p class="govmem-muted" data-govmem-nobody hidden>{{ __('office_membership::member.handover_nobody') }}</p>
                @error('assigned_user_id')<p class="gs-form-row__error" role="alert">{{ $message }}</p>@enderror
                <x-gs::form-row for="handover-note" :label="__('office_membership::member.handover_note')" :help="__('office_membership::member.handover_note_help')">
                    <textarea class="form-control" id="handover-note" name="note" rows="2" maxlength="500" aria-describedby="handover-note-help">{{ $reopen ? old('note') : '' }}</textarea>
                </x-gs::form-row>
                <div>
                    <strong>{{ __('office_membership::member.next_title') }}</strong>
                    <ul class="govmem-effects">
                        <li><i class="fas fa-envelope-open-text" aria-hidden="true"></i><span>{{ __('office_membership::member.handover_next_request') }}</span></li>
                        <li><i class="fas fa-lock" aria-hidden="true"></i><span>{{ __('office_membership::member.handover_next_stays') }}</span></li>
                        <li><i class="fas fa-circle-check" aria-hidden="true"></i><span>{{ __('office_membership::member.handover_next_accepts') }}</span></li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-dismiss="modal">{{ __('office_membership::member.cancel') }}</button>
                <button type="submit" class="gs-btn gs-btn--primary">{{ __('office_membership::member.handover_send') }}</button>
            </div>
        </form>
    </div>
</div>
